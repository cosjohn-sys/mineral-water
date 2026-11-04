<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    #[TestWith(['daily', 2])]
    #[TestWith(['monthly', 1])]
    public function test_sample_transactions_distinguish_revenue_collections_reversals_credit_expenses_stock_and_gallons(string $groupBy, int $periodCount): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $trackedProduct = DB::table('products')->insertGetId(['name' => 'A Refill', 'price' => '100.00', 'stock_quantity' => 7]);
        DB::table('products')->insert([
            ['name' => 'B Untracked', 'price' => '25.00', 'stock_quantity' => null, 'is_active' => true],
            ['name' => 'C Inactive', 'price' => '25.00', 'stock_quantity' => 0, 'is_active' => false],
        ]);
        $oldSale = DB::table('sales')->insertGetId(['sale_number' => 'OLD', 'user_id' => $user->id, 'customer_id' => $customerId, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-09-30 09:00:00']);
        $firstSale = DB::table('sales')->insertGetId(['sale_number' => 'FIRST', 'user_id' => $user->id, 'customer_id' => $customerId, 'sale_type' => 'walk_in', 'total_amount' => '200.00', 'status' => 'completed', 'sold_at' => '2026-10-01 00:00:00']);
        $secondSale = DB::table('sales')->insertGetId(['sale_number' => 'SECOND', 'user_id' => $user->id, 'sale_type' => 'delivery', 'delivery_address' => 'Main Street', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-02 23:59:59']);
        $cancelledSale = DB::table('sales')->insertGetId(['sale_number' => 'CANCELLED', 'user_id' => $user->id, 'customer_id' => $customerId, 'sale_type' => 'walk_in', 'total_amount' => '999.00', 'status' => 'cancelled', 'sold_at' => '2026-10-01 12:00:00']);
        DB::table('sale_items')->insert([
            ['sale_id' => $firstSale, 'product_id' => $trackedProduct, 'quantity' => 1, 'unit_price' => '100.00', 'subtotal' => '100.00'],
            ['sale_id' => $firstSale, 'product_id' => $trackedProduct, 'quantity' => 1, 'unit_price' => '100.00', 'subtotal' => '100.00'],
            ['sale_id' => $secondSale, 'product_id' => $trackedProduct, 'quantity' => 1, 'unit_price' => '100.00', 'subtotal' => '100.00'],
            ['sale_id' => $cancelledSale, 'product_id' => $trackedProduct, 'quantity' => 999, 'unit_price' => '1.00', 'subtotal' => '999.00'],
        ]);
        $oldPayment = DB::table('payments')->insertGetId(['sale_id' => $oldSale, 'amount' => '40.00', 'payment_method' => 'cash', 'paid_at' => '2026-09-30 12:00:00']);
        $oldCollection = DB::table('payments')->insertGetId(['sale_id' => $oldSale, 'amount' => '30.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-01 09:00:00']);
        $cancelledPayment = DB::table('payments')->insertGetId(['sale_id' => $cancelledSale, 'amount' => '15.00', 'payment_method' => 'gcash', 'paid_at' => '2026-10-01 10:00:00']);
        DB::table('payments')->insert([
            ['sale_id' => $firstSale, 'amount' => '80.00', 'payment_method' => 'gcash', 'paid_at' => '2026-10-01 11:00:00'],
            ['sale_id' => $secondSale, 'amount' => '20.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-02 23:59:59'],
            ['sale_id' => $secondSale, 'amount' => '5.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-04 10:00:00'],
            ['sale_id' => $firstSale, 'amount' => '-500.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-01 11:00:00'],
            ['sale_id' => $firstSale, 'amount' => '0.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-01 11:00:00'],
        ]);
        DB::table('payment_reversals')->insert([
            ['payment_id' => $oldPayment, 'user_id' => $user->id, 'reason' => 'Refunded', 'reversed_at' => '2026-10-02 12:00:00'],
            ['payment_id' => $oldCollection, 'user_id' => $user->id, 'reason' => 'Invalid reversal before payment', 'reversed_at' => '2026-09-29 12:00:00'],
            ['payment_id' => $cancelledPayment, 'user_id' => $user->id, 'reason' => 'Later refund', 'reversed_at' => '2026-10-03 12:00:00'],
        ]);
        DB::table('expenses')->insert([
            ['description' => 'Fuel', 'category' => 'Transport', 'amount' => '10.10', 'expense_date' => '2026-10-01'],
            ['description' => 'Power', 'category' => 'Utilities', 'amount' => '5.20', 'expense_date' => '2026-10-02'],
            ['description' => 'Outside range', 'category' => 'Utilities', 'amount' => '900.00', 'expense_date' => '2026-10-03'],
        ]);
        DB::table('gallon_exchanges')->insert([
            ['sale_id' => $firstSale, 'filled_gallons_out' => 3, 'empty_gallons_returned' => 1],
            ['sale_id' => $secondSale, 'filled_gallons_out' => 0, 'empty_gallons_returned' => 2],
            ['sale_id' => $cancelledSale, 'filled_gallons_out' => 99, 'empty_gallons_returned' => 0],
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-02', 'group_by' => $groupBy]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Reports/Index')
            ->where('summary.sale_count', 2)->where('summary.sales_revenue', 300)->where('summary.gross_collections', 145)
            ->where('summary.reversals', 40)->where('summary.net_collections', 105)->where('summary.cash_collections', 10)
            ->where('summary.gcash_collections', 95)->where('summary.expenses', 15.3)->where('outstandingCredit', 270)
            ->where('summary.filled_gallons_out', 3)->where('summary.empty_gallons_returned', 3)->where('summary.net_gallons_out', 0)
            ->has('periods.data', $periodCount)->has('creditSales.data', 3)->where('creditSales.data.0.sale_number', 'OLD')
            ->where('creditSales.data.0.outstanding_balance', 70)->where('stockSummary.current_units', 7)
            ->where('stockSummary.tracked_products', 2)->where('stockSummary.untracked_products', 1)->where('stock.data.0.units_sold', 3));
        $this->get(route('reports.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-01']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales_revenue', 200)->where('summary.gross_collections', 125)->where('summary.reversals', 0)->where('outstandingCredit', 150));
        $this->get(route('reports.index', ['date_from' => '2026-10-03', 'date_to' => '2026-10-03']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales_revenue', 0)->where('summary.gross_collections', 0)->where('summary.reversals', 15)
            ->where('summary.net_collections', -15)->where('summary.gcash_collections', -15));
    }

    public function test_monthly_groups_keep_years_separate_and_respect_partial_month_ranges(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        foreach (['2025-12-31 23:59:59', '2026-01-01 00:00:00', '2026-01-15 12:00:00'] as $index => $soldAt) {
            DB::table('sales')->insert(['sale_number' => 'SALE-'.$index, 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '10.00', 'status' => 'completed', 'sold_at' => $soldAt]);
        }

        $this->actingAs($user)->get(route('reports.index', ['date_from' => '2025-12-31', 'date_to' => '2026-01-01', 'group_by' => 'monthly']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('periods.data', 2)->where('periods.data.0.period', '2026-01')->where('periods.data.0.sales_revenue', 10)
            ->where('periods.data.1.period', '2025-12')->where('summary.sales_revenue', 20)->where('outstandingCredit', 20));
    }

    public function test_report_sections_paginate_independently_without_reducing_summary_totals(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        for ($number = 1; $number <= 13; $number++) {
            DB::table('sales')->insert(['sale_number' => 'SALE-'.$number, 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => sprintf('2026-10-%02d 12:00:00', $number)]);
            DB::table('products')->insert(['name' => sprintf('Product %02d', $number), 'price' => '10.00', 'stock_quantity' => 1]);
        }
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-13', 'group_by' => 'daily', 'per_page' => 10];

        $response = $this->actingAs($user)->get(route('reports.index', $filters));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->has('periods.data', 10)->has('creditSales.data', 10)->has('stock.data', 10)
            ->where('summary.sales_revenue', 1300)->where('outstandingCredit', 1300)->where('stockSummary.current_units', 13));
        $this->assertStringContainsString('periods_page=2', $response->inertiaProps('periods.next_page_url'));
        $this->assertStringContainsString('date_from=2026-10-01', $response->inertiaProps('periods.next_page_url'));
        $this->get(route('reports.index', [...$filters, 'periods_page' => 2, 'credit_page' => 2, 'stock_page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('periods.data', 3)->has('creditSales.data', 3)->has('stock.data', 3)->where('summary.sales_revenue', 1300)->where('outstandingCredit', 1300));
    }

    public function test_empty_reports_default_to_the_end_dates_month_and_have_zero_totals(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));

        $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.date_from', '2026-10-01')->where('filters.date_to', '2026-10-24')->where('summary.sales_revenue', 0)
            ->where('summary.net_collections', 0)->where('summary.expenses', 0)->where('outstandingCredit', 0)
            ->where('stockSummary.current_units', 0)->has('periods.data', 0)->has('stock.data', 0)->has('creditSales.data', 0));
        $this->get(route('reports.index', ['date_to' => '2026-09-30']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.date_from', '2026-09-01'));
    }

    #[TestWith(['date_from', 'invalid'])]
    #[TestWith(['date_from', '2026-02-30'])]
    #[TestWith(['date_to', ['invalid']])]
    #[TestWith(['date_to', '2026-10-01'])]
    #[TestWith(['date_to', '2026-10-25'])]
    #[TestWith(['group_by', 'YEAR(event_date); DROP TABLE sales'])]
    #[TestWith(['per_page', 999])]
    #[TestWith(['periods_page', 0])]
    #[TestWith(['credit_page', 0])]
    #[TestWith(['stock_page', 0])]
    public function test_report_filters_reject_invalid_dates_grouping_and_pagination(string $field, mixed $value): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $filters = ['date_from' => '2026-10-02', 'date_to' => '2026-10-24'];
        $filters[$field] = $value;

        $this->actingAs(User::factory()->create())->get(route('reports.index', $filters))->assertSessionHasErrors([$field]);
    }
}
