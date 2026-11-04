<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'group_by' => ['nullable', 'in:daily,monthly'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'periods_page' => ['nullable', 'integer', 'min:1'],
            'credit_page' => ['nullable', 'integer', 'min:1'],
            'stock_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $dateTo = $request->input('date_to') ?: now()->toDateString();
        $dateFrom = $request->input('date_from') ?: Carbon::parse($dateTo)->startOfMonth()->toDateString();
        if ($dateFrom > $dateTo) {
            throw ValidationException::withMessages(['date_to' => 'The end date must be on or after the start date.']);
        }
        $fromTimestamp = $dateFrom.' 00:00:00';
        $toTimestamp = $dateTo.' 23:59:59';
        $groupBy = $request->input('group_by') ?: 'daily';
        $perPage = $request->integer('per_page') ?: 10;

        $salesEvents = DB::table('sales')
        ->leftJoin('gallon_exchanges', 'gallon_exchanges.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$fromTimestamp, $toTimestamp])
            ->where('sales.sold_at', '<=', now())
            ->selectRaw('DATE(sales.sold_at) AS event_date, 1 AS sale_count, ROUND(sales.total_amount * 100) AS revenue_cents, 0 AS collection_cents, 0 AS reversal_cents, 0 AS cash_cents, 0 AS gcash_cents, 0 AS expense_cents, COALESCE(gallon_exchanges.filled_gallons_out, 0) AS filled_gallons_out, COALESCE(gallon_exchanges.empty_gallons_returned, 0) AS empty_gallons_returned');
        $collectionEvents = DB::table('payments')
        ->where('amount', '>', 0)
        ->whereIn('payment_method', ['cash', 'gcash'])
            ->whereBetween('paid_at', [$fromTimestamp, $toTimestamp])
            ->where('paid_at', '<=', now())
            ->selectRaw("DATE(paid_at) AS event_date, 0 AS sale_count, 0 AS revenue_cents, ROUND(amount * 100) AS collection_cents, 0 AS reversal_cents, CASE WHEN payment_method = 'cash' THEN ROUND(amount * 100) ELSE 0 END AS cash_cents, CASE WHEN payment_method = 'gcash' THEN ROUND(amount * 100) ELSE 0 END AS gcash_cents, 0 AS expense_cents, 0 AS filled_gallons_out, 0 AS empty_gallons_returned");
        $reversalEvents = DB::table('payment_reversals')
        ->join('payments', 'payments.id', '=', 'payment_reversals.payment_id')
            ->where('payments.amount', '>', 0)
            ->whereIn('payments.payment_method', ['cash', 'gcash'])
            ->whereColumn('payment_reversals.reversed_at', '>=', 'payments.paid_at')
            ->where('payment_reversals.reversed_at', '<=', now())
            ->whereBetween('payment_reversals.reversed_at', [$fromTimestamp, $toTimestamp])
            ->selectRaw("DATE(payment_reversals.reversed_at) AS event_date, 0 AS sale_count, 0 AS revenue_cents, 0 AS collection_cents, ROUND(payments.amount * 100) AS reversal_cents, CASE WHEN payments.payment_method = 'cash' THEN -ROUND(payments.amount * 100) ELSE 0 END AS cash_cents, CASE WHEN payments.payment_method = 'gcash' THEN -ROUND(payments.amount * 100) ELSE 0 END AS gcash_cents, 0 AS expense_cents, 0 AS filled_gallons_out, 0 AS empty_gallons_returned");
        $expenseEvents = DB::table('expenses')
        ->where('amount', '>', 0)
        ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw('expense_date AS event_date, 0 AS sale_count, 0 AS revenue_cents, 0 AS collection_cents, 0 AS reversal_cents, 0 AS cash_cents, 0 AS gcash_cents, ROUND(amount * 100) AS expense_cents, 0 AS filled_gallons_out, 0 AS empty_gallons_returned');
        $events = $salesEvents->unionAll($collectionEvents)->unionAll($reversalEvents)->unionAll($expenseEvents);
        $activity = DB::query()->fromSub($events, 'activity');
        $summary = (clone $activity)
        ->selectRaw('COALESCE(SUM(sale_count), 0) AS sale_count, COALESCE(SUM(revenue_cents), 0) / 100.0 AS sales_revenue, COALESCE(SUM(collection_cents), 0) / 100.0 AS gross_collections, COALESCE(SUM(reversal_cents), 0) / 100.0 AS reversals, COALESCE(SUM(collection_cents - reversal_cents), 0) / 100.0 AS net_collections, COALESCE(SUM(cash_cents), 0) / 100.0 AS cash_collections, COALESCE(SUM(gcash_cents), 0) / 100.0 AS gcash_collections, COALESCE(SUM(expense_cents), 0) / 100.0 AS expenses, COALESCE(SUM(filled_gallons_out), 0) AS filled_gallons_out, COALESCE(SUM(empty_gallons_returned), 0) AS empty_gallons_returned')
            ->selectRaw('COALESCE(SUM(CAST(filled_gallons_out AS DECIMAL(20, 0)) - CAST(empty_gallons_returned AS DECIMAL(20, 0))), 0) AS net_gallons_out')
            ->first();
        $periodExpression = $groupBy === 'monthly' ? 'SUBSTR(event_date, 1, 7)' : 'event_date';
        $periods = $activity->selectRaw($periodExpression.' AS period, SUM(sale_count) AS sale_count, SUM(revenue_cents) / 100.0 AS sales_revenue, SUM(collection_cents) / 100.0 AS gross_collections, SUM(reversal_cents) / 100.0 AS reversals, SUM(collection_cents - reversal_cents) / 100.0 AS net_collections, SUM(expense_cents) / 100.0 AS expenses, SUM(filled_gallons_out) AS filled_gallons_out, SUM(empty_gallons_returned) AS empty_gallons_returned')
            ->groupByRaw($periodExpression)
            ->orderByDesc('period')
            ->paginate($perPage, ['*'], 'periods_page')->withQueryString();

        $paidThroughEndDate = DB::table('payments')
        ->select('sale_id')->selectRaw('SUM(ROUND(amount * 100)) AS paid_cents')
            ->where('amount', '>', 0)
            ->whereIn('payment_method', ['cash', 'gcash'])
            ->where('paid_at', '<=', $toTimestamp)->where('paid_at', '<=', now())
            ->whereNotExists(function (Builder $query) use ($toTimestamp): void {
                $query->selectRaw('1')->from('payment_reversals')
                ->whereColumn('payment_id', 'payments.id')
                    ->whereColumn('reversed_at', '>=', 'payments.paid_at')
                    ->where('reversed_at', '<=', $toTimestamp)
                    ->where('reversed_at', '<=', now());
            })->groupBy('sale_id');
        $credit = DB::table('sales')->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoinSub($paidThroughEndDate, 'payment_totals', 'payment_totals.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '<=', $toTimestamp)
            ->where('sales.sold_at', '<=', now())
            ->whereRaw('ROUND(sales.total_amount * 100) > COALESCE(payment_totals.paid_cents, 0)');
        $outstandingCredit = (clone $credit)
        ->selectRaw('COALESCE(SUM(ROUND(sales.total_amount * 100) - COALESCE(payment_totals.paid_cents, 0)), 0) / 100.0 AS outstanding_credit')
        ->value('outstanding_credit');
        $creditSales = $credit->select('sales.id', 'sales.sale_number', 'sales.sold_at', 'sales.total_amount', 'customers.name as customer_name')
            ->selectRaw('(ROUND(sales.total_amount * 100) - COALESCE(payment_totals.paid_cents, 0)) / 100.0 AS outstanding_balance')
            ->orderBy('sales.sold_at')
            ->orderBy('sales.id')
            ->paginate($perPage, ['*'], 'credit_page')
            ->withQueryString();

        $unitsSold = DB::table('sale_items')
        ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$fromTimestamp, $toTimestamp])
            ->where('sales.sold_at', '<=', now())
            ->select('sale_items.product_id')
            ->selectRaw('SUM(sale_items.quantity) AS units_sold')
            ->groupBy('sale_items.product_id');
        $stock = DB::table('products')->leftJoinSub($unitsSold, 'sold_units', 'sold_units.product_id', '=', 'products.id')
            ->select('products.id', 'products.name', 'products.is_active', 'products.stock_quantity')
            ->selectRaw('COALESCE(sold_units.units_sold, 0) AS units_sold')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->paginate($perPage, ['*'], 'stock_page')
            ->withQueryString();
        $stockSummary = DB::table('products')->selectRaw('COUNT(*) AS product_count, COALESCE(SUM(CASE WHEN stock_quantity IS NOT NULL THEN 1 ELSE 0 END), 0) AS tracked_products, COALESCE(SUM(CASE WHEN stock_quantity IS NULL THEN 1 ELSE 0 END), 0) AS untracked_products, COALESCE(SUM(stock_quantity), 0) AS current_units')
        ->first();

        return Inertia::render('Reports/Index', [
            'summary' => $summary, 'periods' => $periods, 'outstandingCredit' => $outstandingCredit,
            'creditSales' => $creditSales, 'stock' => $stock, 'stockSummary' => $stockSummary,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'group_by' => $groupBy, 'per_page' => $perPage],
            'timezone' => config('app.timezone'),
        ]);
    }
}
