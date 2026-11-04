<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $timestamp = now();
        $date = $timestamp->toDateString();
        $startOfDay = $timestamp->copy()->startOfDay();

        $sales = DB::table('sales')
        ->where('status', 'completed')
        ->whereBetween('sold_at', [$startOfDay, $timestamp])
            ->selectRaw('COUNT(*) AS sale_count, COALESCE(SUM(ROUND(total_amount * 100)), 0) / 100.0 AS revenue')
            ->selectRaw("COUNT(CASE WHEN sale_type = 'walk_in' THEN 1 END) AS walk_in_count, COUNT(CASE WHEN sale_type = 'delivery' THEN 1 END) AS delivery_count")
            ->first();

        $paymentEvents = DB::table('payments')
        ->where('amount', '>', 0)
        ->whereIn('payment_method', ['cash', 'gcash'])
            ->whereBetween('paid_at', [$startOfDay, $timestamp])
            ->selectRaw("ROUND(amount * 100) AS collected_cents, 0 AS reversed_cents, CASE WHEN payment_method = 'cash' THEN ROUND(amount * 100) ELSE 0 END AS cash_cents, CASE WHEN payment_method = 'gcash' THEN ROUND(amount * 100) ELSE 0 END AS gcash_cents");
        $reversalEvents = DB::table('payment_reversals')
        ->join('payments', 'payments.id', '=', 'payment_reversals.payment_id')
            ->where('payments.amount', '>', 0)
            ->whereIn('payments.payment_method', ['cash', 'gcash'])
            ->whereColumn('payment_reversals.reversed_at', '>=', 'payments.paid_at')
            ->whereBetween('payment_reversals.reversed_at', [$startOfDay, $timestamp])
            ->selectRaw("0 AS collected_cents, ROUND(payments.amount * 100) AS reversed_cents, CASE WHEN payments.payment_method = 'cash' THEN -ROUND(payments.amount * 100) ELSE 0 END AS cash_cents, CASE WHEN payments.payment_method = 'gcash' THEN -ROUND(payments.amount * 100) ELSE 0 END AS gcash_cents");
        $collections = DB::query()->fromSub($paymentEvents->unionAll($reversalEvents), 'collection_events')
            ->selectRaw('COALESCE(SUM(collected_cents), 0) / 100.0 AS gross, COALESCE(SUM(reversed_cents), 0) / 100.0 AS reversals, COALESCE(SUM(collected_cents - reversed_cents), 0) / 100.0 AS net, COALESCE(SUM(cash_cents), 0) / 100.0 AS cash, COALESCE(SUM(gcash_cents), 0) / 100.0 AS gcash')
            ->first();

        $paymentTotals = DB::table('payments')
        ->select('sale_id')
        ->selectRaw('SUM(ROUND(amount * 100)) AS paid_cents')
            ->where('amount', '>', 0)
            ->whereIn('payment_method', ['cash', 'gcash'])
            ->where('paid_at', '<=', $timestamp)
            ->whereNotExists(function (Builder $query) use ($timestamp): void {
                $query->selectRaw('1')
                ->from('payment_reversals')
                ->whereColumn('payment_id', 'payments.id')
                    ->whereColumn('reversed_at', '>=', 'payments.paid_at')
                    ->where('reversed_at', '<=', $timestamp);
            })->groupBy('sale_id');
        $credit = DB::table('sales')
        ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')->where('sales.sold_at', '<=', $timestamp)
            ->whereRaw('ROUND(sales.total_amount * 100) > COALESCE(payment_totals.paid_cents, 0)')
            ->selectRaw('COUNT(*) AS sale_count, COALESCE(SUM(ROUND(sales.total_amount * 100) - COALESCE(payment_totals.paid_cents, 0)), 0) / 100.0 AS outstanding')
            ->first();

        $expenses = DB::table('expenses')
        ->where('expense_date', $date)
        ->where('amount', '>', 0)
            ->selectRaw('COALESCE(SUM(ROUND(amount * 100)), 0) / 100.0 AS total')
            ->value('total');
        $gallons = DB::table('gallon_exchanges')
        ->join('sales', 'sales.id', '=', 'gallon_exchanges.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$startOfDay, $timestamp])
            ->selectRaw('COALESCE(SUM(filled_gallons_out), 0) AS filled_out, COALESCE(SUM(empty_gallons_returned), 0) AS empty_returned')
            ->selectRaw('COALESCE(SUM(CAST(filled_gallons_out AS DECIMAL(20, 0)) - CAST(empty_gallons_returned AS DECIMAL(20, 0))), 0) AS net_out')
            ->first();
        $stock = DB::table('products')
            ->selectRaw('COALESCE(SUM(stock_quantity), 0) AS current_units, COUNT(stock_quantity) AS tracked_products, COUNT(*) - COUNT(stock_quantity) AS untracked_products')
            ->selectRaw('COUNT(CASE WHEN is_active = 1 AND stock_quantity = 0 THEN 1 END) AS active_out_of_stock')
            ->first();

        $overviewStart = $startOfDay->copy()->subDays(6);
        $dailyRevenue = DB::table('sales')
        ->where('status', 'completed')
        ->whereBetween('sold_at', [$overviewStart, $timestamp])
            ->selectRaw('DATE(sold_at) AS sale_date, SUM(ROUND(total_amount * 100)) / 100.0 AS revenue')
            ->groupByRaw('DATE(sold_at)')
            ->pluck('revenue', 'sale_date');
        $salesOverview = [];
        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $day = $overviewStart->copy()->addDays($dayOffset);
            $salesOverview[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('D'),
                'revenue' => $dailyRevenue->get($day->toDateString(), 0),
            ];
        }

        $topProducts = DB::table('sale_items')
        ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$overviewStart, $timestamp])
            ->select('products.id', 'products.name')
            ->selectRaw('SUM(sale_items.quantity) AS units_sold')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('units_sold')
            ->orderBy('products.id')
            ->limit(4)
            ->get();
        $recentSales = DB::table('sales')
        ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.sale_id', '=', 'sales.id')
            ->where('sales.sold_at', '<=', $timestamp)
            ->select('sales.id', 'sales.sale_number', 'sales.sale_type', 'sales.total_amount', 'sales.status', 'sales.sold_at', 'customers.name as customer_name')
            ->selectRaw('COALESCE(payment_totals.paid_cents, 0) / 100.0 AS amount_paid')
            ->orderByDesc('sales.sold_at')
            ->orderByDesc('sales.id')
            ->limit(5)->get();

        return Inertia::render('Dashboard', [
            'date' => $date,
            'asOf' => $timestamp->toDateTimeString(),
            'timezone' => config('app.timezone'),
            'sales' => $sales,
            'collections' => $collections,
            'credit' => $credit,
            'expenses' => $expenses,
            'gallons' => $gallons,
            'stock' => $stock,
            'salesOverview' => $salesOverview,
            'topProducts' => $topProducts,
            'recentSales' => $recentSales,
        ]);
    }
}
