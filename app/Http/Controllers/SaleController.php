<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $dateFrom = $request->input('date_from') ?: '';
        $dateTo = $request->input('date_to') ?: '';
        $perPage = $request->integer('per_page') ?: 10;
        $paymentTotals = DB::table('payments')->select('sale_id')
            ->selectRaw('SUM(ROUND(amount * 100)) AS paid_cents')
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('payment_reversals')->whereColumn('payment_id', 'payments.id')
                    ->whereColumn('reversed_at', '>=', 'payments.paid_at')->where('reversed_at', '<=', now());
            })
            ->where('amount', '>', 0)->whereIn('payment_method', ['cash', 'gcash'])->where('paid_at', '<=', now())->groupBy('sale_id');
        $sales = DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.sale_id', '=', 'sales.id')
            ->select('sales.id', 'sales.sale_number', 'sales.sale_type', 'sales.total_amount', 'sales.status', 'sales.sold_at', 'customers.name as customer_name')
            ->selectRaw('COALESCE(payment_totals.paid_cents, 0) / 100.0 AS amount_paid')
            ->selectRaw("CASE WHEN sales.status = 'completed' THEN (ROUND(sales.total_amount * 100) - COALESCE(payment_totals.paid_cents, 0)) / 100.0 ELSE 0 END AS outstanding_balance")
            ->when($dateFrom !== '', function (Builder $query) use ($dateFrom): void {
                $query->where('sales.sold_at', '>=', $dateFrom.' 00:00:00');
            })
            ->when($dateTo !== '', function (Builder $query) use ($dateTo): void {
                $query->where('sales.sold_at', '<=', $dateTo.' 23:59:59');
            })
            ->orderByDesc('sales.sold_at')
            ->orderByDesc('sales.id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'per_page' => $perPage],
            'timezone' => config('app.timezone'),
        ]);
    }

    public function create(): Response
    {
        $timestamp = now();
        $startOfDay = $timestamp->copy()->startOfDay();
        $todaySales = DB::table('sales')->where('status', 'completed')->whereBetween('sold_at', [$startOfDay, $timestamp])
            ->selectRaw('COUNT(*) AS sale_count, COALESCE(SUM(ROUND(total_amount * 100)), 0) / 100.0 AS revenue')->first();
        $todayUnits = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')->whereBetween('sales.sold_at', [$startOfDay, $timestamp])->sum('sale_items.quantity');

        return Inertia::render('Sales/Create', [
            'customers' => DB::table('customers')->select('id', 'name', 'phone', 'address')->orderBy('name')->orderBy('id')->get(),
            'products' => DB::table('products')->select('id', 'name', 'price', 'stock_quantity')->where('is_active', true)->orderBy('name')->orderBy('id')->get(),
            'submissionToken' => (string) Str::uuid(),
            'todaySummary' => ['sale_count' => $todaySales->sale_count, 'revenue' => $todaySales->revenue, 'units_sold' => $todayUnits],
            'timezone' => config('app.timezone'),
        ]);
    }

    public function store(SaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;
        $submissionToken = Str::lower($data['submission_token']);

        $saleId = DB::transaction(function () use ($data, $userId, $submissionToken): int {
            DB::table('users')->where('id', $userId)->lockForUpdate()->firstOrFail();

            $existingSaleId = DB::table('sales')->where('user_id', $userId)
                ->where('submission_token', $submissionToken)->lockForUpdate()->value('id');

            if ($existingSaleId !== null) {
                return (int) $existingSaleId;
            }

            if (! empty($data['customer_id'])) {
                $customer = DB::table('customers')->where('id', $data['customer_id'])->lockForUpdate()->first();

                if ($customer === null) {
                    throw ValidationException::withMessages(['customer_id' => 'The selected customer is no longer available.']);
                }
            }

            $products = DB::table('products')->whereIn('id', array_column($data['items'], 'product_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $timestamp = now();
            $totalCents = 0;
            $saleItems = [];

            foreach ($data['items'] as $index => $item) {
                $product = $products->get($item['product_id']);

                if ($product === null || ! $product->is_active) {
                    throw ValidationException::withMessages(["items.$index.product_id" => 'Select an active product.']);
                }

                $quantity = (int) $item['quantity'];

                if ($product->stock_quantity !== null && $quantity > $product->stock_quantity) {
                    throw ValidationException::withMessages(["items.$index.quantity" => 'There is not enough stock for this product.']);
                }

                $priceParts = explode('.', (string) $product->price, 2);
                $unitPriceCents = (int) $priceParts[0] * 100 + (int) str_pad($priceParts[1] ?? '', 2, '0');

                if ($unitPriceCents > intdiv(9999999999 - $totalCents, $quantity)) {
                    throw ValidationException::withMessages(['items' => 'The sale total must not exceed 99,999,999.99.']);
                }

                $subtotalCents = $unitPriceCents * $quantity;
                $totalCents += $subtotalCents;
                $saleItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => sprintf('%d.%02d', intdiv($unitPriceCents, 100), $unitPriceCents % 100),
                    'subtotal' => sprintf('%d.%02d', intdiv($subtotalCents, 100), $subtotalCents % 100),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            $initialPaymentCents = 0;

            if (($data['initial_payment_type'] ?? 'none') === 'full') {
                $initialPaymentCents = $totalCents;
            } elseif (($data['initial_payment_type'] ?? 'none') === 'partial') {
                $amountParts = explode('.', (string) $data['initial_payment_amount'], 2);
                $initialPaymentCents = (int) $amountParts[0] * 100 + (int) str_pad($amountParts[1] ?? '', 2, '0');
            }

            if ($initialPaymentCents > $totalCents) {
                throw ValidationException::withMessages(['initial_payment_amount' => 'The initial payment must not exceed the sale total.']);
            }

            $saleId = DB::table('sales')->insertGetId([
                'sale_number' => 'SALE-'.Str::ulid(),
                'submission_token' => $submissionToken,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $userId,
                'sale_type' => $data['sale_type'],
                'delivery_address' => $data['sale_type'] === 'delivery' ? $data['delivery_address'] : null,
                'total_amount' => sprintf('%d.%02d', intdiv($totalCents, 100), $totalCents % 100),
                'status' => 'completed',
                'sold_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            foreach ($saleItems as $saleItem) {
                DB::table('sale_items')->insert(['sale_id' => $saleId, ...$saleItem]);

                if ($products->get($saleItem['product_id'])->stock_quantity !== null) {
                    DB::table('products')->where('id', $saleItem['product_id'])->decrement('stock_quantity', $saleItem['quantity'], ['updated_at' => $timestamp]);
                }
            }

            if ($initialPaymentCents > 0) {
                DB::table('payments')->insert([
                    'sale_id' => $saleId,
                    'user_id' => $userId,
                    'submission_token' => $submissionToken,
                    'amount' => sprintf('%d.%02d', intdiv($initialPaymentCents, 100), $initialPaymentCents % 100),
                    'payment_method' => $data['payment_method'],
                    'paid_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            if ($data['track_gallon_exchange'] ?? false) {
                DB::table('gallon_exchanges')->insert([
                    'sale_id' => $saleId,
                    'filled_gallons_out' => (int) $data['filled_gallons_out'],
                    'empty_gallons_returned' => (int) $data['empty_gallons_returned'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            return $saleId;
        }, 3);

        return Redirect::route('sales.show', $saleId)->with('status', 'Sale recorded.');
    }

    public function show(int $sale): Response
    {
        $paymentTotals = DB::table('payments')->select('sale_id')
            ->selectRaw('SUM(ROUND(amount * 100)) AS paid_cents')
            ->selectRaw("SUM(CASE WHEN payment_method = 'cash' THEN ROUND(amount * 100) ELSE 0 END) AS cash_cents")
            ->selectRaw("SUM(CASE WHEN payment_method = 'gcash' THEN ROUND(amount * 100) ELSE 0 END) AS gcash_cents")
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('payment_reversals')->whereColumn('payment_id', 'payments.id')
                    ->whereColumn('reversed_at', '>=', 'payments.paid_at')->where('reversed_at', '<=', now());
            })
            ->where('amount', '>', 0)->whereIn('payment_method', ['cash', 'gcash'])->where('paid_at', '<=', now())->groupBy('sale_id');
        $saleDetails = DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.sale_id', '=', 'sales.id')
            ->select('sales.id', 'sales.sale_number', 'sales.sale_type', 'sales.delivery_address', 'sales.total_amount', 'sales.status', 'sales.sold_at', 'customers.name as customer_name', 'users.name as recorded_by')
            ->selectRaw('COALESCE(payment_totals.paid_cents, 0) / 100.0 AS amount_paid')
            ->selectRaw('COALESCE(payment_totals.cash_cents, 0) / 100.0 AS cash_received')
            ->selectRaw('COALESCE(payment_totals.gcash_cents, 0) / 100.0 AS gcash_received')
            ->selectRaw("CASE WHEN sales.status = 'completed' THEN (ROUND(sales.total_amount * 100) - COALESCE(payment_totals.paid_cents, 0)) / 100.0 ELSE 0 END AS outstanding_balance")
            ->where('sales.id', $sale)->firstOrFail();
        $items = DB::table('sale_items')->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sale_items.sale_id', $sale)
            ->select('sale_items.id', 'products.name as product_name', 'sale_items.quantity', 'sale_items.unit_price', 'sale_items.subtotal')
            ->orderBy('sale_items.id')->get();

        return Inertia::render('Sales/Show', [
            'sale' => $saleDetails,
            'items' => $items,
            'payments' => DB::table('payments')->leftJoin('users', 'users.id', '=', 'payments.user_id')
                ->leftJoin('payment_reversals', function (JoinClause $join): void {
                    $join->on('payment_reversals.payment_id', '=', 'payments.id')->on('payment_reversals.reversed_at', '>=', 'payments.paid_at')
                        ->where('payment_reversals.reversed_at', '<=', now());
                })
                ->where('payments.sale_id', $sale)->where('payments.amount', '>', 0)->whereIn('payments.payment_method', ['cash', 'gcash'])
                ->select('payments.id', 'payments.amount', 'payments.payment_method', 'payments.paid_at', 'users.name as recorded_by', 'payment_reversals.reversed_at', 'payment_reversals.reason as reversal_reason')
                ->orderByDesc('payments.paid_at')->orderByDesc('payments.id')->get(),
            'paymentSubmissionToken' => (string) Str::uuid(),
            'gallonExchange' => DB::table('gallon_exchanges')->where('sale_id', $sale)
                ->select('id', 'filled_gallons_out', 'empty_gallons_returned', 'created_at', 'updated_at')->first(),
            'timezone' => config('app.timezone'),
            'status' => session('status'),
        ]);
    }
}
