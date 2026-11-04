<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) $request->input('search', ''));
        $perPage = $request->integer('per_page') ?: 10;
        $paymentTotals = DB::table('payments')->select('sale_id')
            ->selectRaw('SUM(ROUND(amount * 100)) AS paid_cents')
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('payment_reversals')->whereColumn('payment_id', 'payments.id')
                    ->whereColumn('reversed_at', '>=', 'payments.paid_at')->where('reversed_at', '<=', now());
            })
            ->where('amount', '>', 0)->whereIn('payment_method', ['cash', 'gcash'])->where('paid_at', '<=', now())->groupBy('sale_id');
        $customerBalances = DB::table('sales')->where('sales.status', 'completed')->where('sales.sold_at', '<=', now())
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.sale_id', '=', 'sales.id')
            ->select('sales.customer_id')
            ->selectRaw('SUM(CASE WHEN ROUND(sales.total_amount * 100) > COALESCE(payment_totals.paid_cents, 0) THEN ROUND(sales.total_amount * 100) - COALESCE(payment_totals.paid_cents, 0) ELSE 0 END) / 100.0 AS outstanding_balance')
            ->groupBy('sales.customer_id');
        $customers = DB::table('customers')
            ->leftJoinSub($customerBalances, 'customer_balances', 'customer_balances.customer_id', '=', 'customers.id')
            ->select('customers.id', 'customers.name', 'customers.phone', 'customers.address')
            ->selectRaw('COALESCE(customer_balances.outstanding_balance, 0) AS outstanding_balance')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('address', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'status' => session('status'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Customers/Form', ['customer' => null]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomerRequest $request): RedirectResponse
    {
        $timestamp = now();
        $customerId = DB::table('customers')->insertGetId([
            ...$request->validated(),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return Redirect::route('customers.show', $customerId)->with('status', 'Customer added.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $customer): Response
    {
        $request->validate([
            'sales_page' => ['nullable', 'integer', 'min:1'],
            'payments_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $customerDetails = DB::table('customers')->where('id', $customer)->firstOrFail();
        $paymentTotals = DB::table('payments')->select('sale_id')
            ->selectRaw('SUM(ROUND(amount * 100)) AS paid_cents')
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('payment_reversals')->whereColumn('payment_id', 'payments.id')
                    ->whereColumn('reversed_at', '>=', 'payments.paid_at')->where('reversed_at', '<=', now());
            })
            ->where('amount', '>', 0)->whereIn('payment_method', ['cash', 'gcash'])->where('paid_at', '<=', now())->groupBy('sale_id');
        $customerSales = DB::table('sales')->where('sales.customer_id', $customer)->where('sales.status', 'completed')
            ->where('sales.sold_at', '<=', now())
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.sale_id', '=', 'sales.id');

        return Inertia::render('Customers/Show', [
            'customer' => $customerDetails,
            'hasSales' => DB::table('sales')->where('customer_id', $customer)->exists(),
            'outstandingBalance' => (clone $customerSales)
                ->selectRaw('COALESCE(SUM(CASE WHEN ROUND(sales.total_amount * 100) > COALESCE(payment_totals.paid_cents, 0) THEN ROUND(sales.total_amount * 100) - COALESCE(payment_totals.paid_cents, 0) ELSE 0 END), 0) / 100.0 AS outstanding_balance')
                ->value('outstanding_balance'),
            'sales' => $customerSales->select('sales.id', 'sales.sale_number', 'sales.sold_at', 'sales.total_amount')
                ->selectRaw('COALESCE(payment_totals.paid_cents, 0) / 100.0 AS amount_paid')
                ->selectRaw('(ROUND(sales.total_amount * 100) - COALESCE(payment_totals.paid_cents, 0)) / 100.0 AS outstanding_balance')
                ->orderByDesc('sales.sold_at')->orderByDesc('sales.id')->paginate(10, ['*'], 'sales_page')->withQueryString(),
            'payments' => DB::table('payments')->join('sales', 'sales.id', '=', 'payments.sale_id')
                ->leftJoin('users', 'users.id', '=', 'payments.user_id')
                ->leftJoin('payment_reversals', function (JoinClause $join): void {
                    $join->on('payment_reversals.payment_id', '=', 'payments.id')->on('payment_reversals.reversed_at', '>=', 'payments.paid_at')
                        ->where('payment_reversals.reversed_at', '<=', now());
                })
                ->where('sales.customer_id', $customer)->where('payments.amount', '>', 0)->whereIn('payments.payment_method', ['cash', 'gcash'])
                ->select('payments.id', 'payments.sale_id', 'payments.amount', 'payments.payment_method', 'payments.paid_at', 'sales.sale_number', 'sales.status as sale_status', 'users.name as recorded_by', 'payment_reversals.reversed_at', 'payment_reversals.reason as reversal_reason')
                ->orderByDesc('payments.paid_at')->orderByDesc('payments.id')->paginate(10, ['*'], 'payments_page')->withQueryString(),
            'timezone' => config('app.timezone'),
            'status' => session('status'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $customer): Response
    {
        return Inertia::render('Customers/Form', [
            'customer' => DB::table('customers')->where('id', $customer)->firstOrFail(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerRequest $request, int $customer): RedirectResponse
    {
        DB::table('customers')->where('id', $customer)->firstOrFail();

        DB::table('customers')->where('id', $customer)->update([
            ...$request->validated(),
            'updated_at' => now(),
        ]);

        return Redirect::route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $customer): RedirectResponse
    {
        DB::transaction(function () use ($customer): void {
            DB::table('customers')->where('id', $customer)->lockForUpdate()->firstOrFail();

            if (DB::table('sales')->where('customer_id', $customer)->exists()) {
                throw ValidationException::withMessages([
                    'customer' => 'Customers with sales cannot be deleted. Their financial history must be preserved.',
                ]);
            }

            DB::table('customers')->where('id', $customer)->delete();
        });

        return Redirect::route('customers.index')->with('status', 'Customer deleted.');
    }
}
