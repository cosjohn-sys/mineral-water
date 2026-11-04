<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_empty_dashboard_has_zero_totals_and_an_explicit_date_and_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('date', '2026-10-24')->where('asOf', '2026-10-24 12:00:00')->where('timezone', 'UTC')
            ->where('sales.sale_count', 0)->where('sales.revenue', 0)->where('sales.walk_in_count', 0)->where('sales.delivery_count', 0)
            ->where('collections.gross', 0)->where('collections.reversals', 0)->where('collections.net', 0)->where('collections.cash', 0)->where('collections.gcash', 0)
            ->where('credit.outstanding', 0)->where('credit.sale_count', 0)->where('expenses', 0)
            ->where('gallons.filled_out', 0)->where('gallons.empty_returned', 0)->where('gallons.net_out', 0)
            ->where('stock.current_units', 0)->where('stock.tracked_products', 0)->where('stock.untracked_products', 0)->where('stock.active_out_of_stock', 0)
            ->has('salesOverview', 7)->where('salesOverview.0.date', '2026-10-18')->where('salesOverview.0.label', 'Sun')
            ->where('salesOverview.0.revenue', 0)->where('salesOverview.6.date', '2026-10-24')->where('salesOverview.6.revenue', 0)
            ->has('topProducts', 0)->has('recentSales', 0));
    }

    public function test_dashboard_keeps_revenue_collections_credit_and_gallons_separate_without_join_multiplication(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $customer = DB::table('customers')->insertGetId(['name' => 'Credit customer']);
        $firstProduct = DB::table('products')->insertGetId(['name' => 'Tracked water', 'price' => '20.00', 'stock_quantity' => 10, 'is_active' => true]);
        $secondProduct = DB::table('products')->insertGetId(['name' => 'Empty stock', 'price' => '60.25', 'stock_quantity' => 0, 'is_active' => true]);
        DB::table('products')->insert([
            ['name' => 'Inactive stock', 'price' => '1.00', 'stock_quantity' => 7, 'is_active' => false],
            ['name' => 'Untracked service', 'price' => '1.00', 'stock_quantity' => null, 'is_active' => true],
        ]);
        $partial = DB::table('sales')->insertGetId(['sale_number' => 'PARTIAL', 'user_id' => $user->id, 'customer_id' => $customer, 'sale_type' => 'walk_in', 'total_amount' => '100.25', 'status' => 'completed', 'sold_at' => '2026-10-24 09:00:00']);
        $unpaid = DB::table('sales')->insertGetId(['sale_number' => 'UNPAID', 'user_id' => $user->id, 'sale_type' => 'delivery', 'delivery_address' => 'Delivery address', 'total_amount' => '200.50', 'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00']);
        $settled = DB::table('sales')->insertGetId(['sale_number' => 'SETTLED', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '60.25', 'status' => 'completed', 'sold_at' => '2026-10-24 11:00:00']);
        $older = DB::table('sales')->insertGetId(['sale_number' => 'OLDER', 'user_id' => $user->id, 'customer_id' => $customer, 'sale_type' => 'walk_in', 'total_amount' => '90.00', 'status' => 'completed', 'sold_at' => '2026-10-23 10:00:00']);
        $cancelled = DB::table('sales')->insertGetId(['sale_number' => 'CANCELLED', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '500.00', 'status' => 'cancelled', 'sold_at' => '2026-10-24 11:30:00']);
        DB::table('sale_items')->insert([
            ['sale_id' => $partial, 'product_id' => $firstProduct, 'quantity' => 2, 'unit_price' => '20.00', 'subtotal' => '40.00'],
            ['sale_id' => $partial, 'product_id' => $secondProduct, 'quantity' => 1, 'unit_price' => '60.25', 'subtotal' => '60.25'],
        ]);
        DB::table('payments')->insert([
            ['sale_id' => $partial, 'amount' => '10.10', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 09:00:00'],
            ['sale_id' => $partial, 'amount' => '20.15', 'payment_method' => 'gcash', 'paid_at' => '2026-10-24 09:30:00'],
            ['sale_id' => $settled, 'amount' => '60.25', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:00:00'],
            ['sale_id' => $cancelled, 'amount' => '40.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:30:00'],
        ]);
        $reversedPayment = DB::table('payments')->insertGetId(['sale_id' => $older, 'amount' => '50.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-23 10:00:00']);
        DB::table('payment_reversals')->insert(['payment_id' => $reversedPayment, 'user_id' => $user->id, 'reason' => 'Refund older collection', 'reversed_at' => '2026-10-24 10:00:00']);
        DB::table('gallon_exchanges')->insert([
            ['sale_id' => $partial, 'filled_gallons_out' => 3, 'empty_gallons_returned' => 2],
            ['sale_id' => $unpaid, 'filled_gallons_out' => 0, 'empty_gallons_returned' => 4],
            ['sale_id' => $settled, 'filled_gallons_out' => 1, 'empty_gallons_returned' => 1],
            ['sale_id' => $older, 'filled_gallons_out' => 4, 'empty_gallons_returned' => 0],
            ['sale_id' => $cancelled, 'filled_gallons_out' => 99, 'empty_gallons_returned' => 0],
        ]);
        DB::table('expenses')->insert([
            ['description' => 'Power', 'category' => 'Utilities', 'amount' => '12.10', 'expense_date' => '2026-10-24'],
            ['description' => 'Fuel', 'category' => 'Transport', 'amount' => '3.25', 'expense_date' => '2026-10-24'],
            ['description' => 'Older expense', 'category' => 'Utilities', 'amount' => '999.00', 'expense_date' => '2026-10-23'],
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sales.sale_count', 3)->where('sales.revenue', 361)->where('sales.walk_in_count', 2)->where('sales.delivery_count', 1)
            ->where('collections.gross', 130.5)->where('collections.reversals', 50)->where('collections.net', 80.5)
            ->where('collections.cash', 60.35)->where('collections.gcash', 20.15)->where('credit.outstanding', 360.5)->where('credit.sale_count', 3)
            ->where('expenses', 15.35)->where('gallons.filled_out', 4)->where('gallons.empty_returned', 7)->where('gallons.net_out', -3)
            ->where('stock.current_units', 17)->where('stock.tracked_products', 3)->where('stock.untracked_products', 1)->where('stock.active_out_of_stock', 1));
        $this->get(route('reports.index', ['date_from' => '2026-10-24', 'date_to' => '2026-10-24']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales_revenue', 361)->where('summary.net_collections', 80.5)->where('outstandingCredit', 360.5)
            ->where('summary.expenses', 15.35)->where('summary.net_gallons_out', -3)->where('stockSummary.current_units', 17));
    }

    public function test_collecting_an_older_credit_sale_is_cash_collection_without_todays_sales_revenue(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $sale = DB::table('sales')->insertGetId(['sale_number' => 'OLD-CREDIT', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-01 09:00:00']);
        DB::table('payments')->insert(['sale_id' => $sale, 'amount' => '35.25', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 00:00:00']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sales.revenue', 0)->where('sales.sale_count', 0)->where('collections.net', 35.25)->where('credit.outstanding', 64.75));
    }

    public function test_reversal_only_day_can_have_negative_collections_and_reopens_credit(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $sale = DB::table('sales')->insertGetId(['sale_number' => 'OLD-SETTLED', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '10.10', 'status' => 'completed', 'sold_at' => '2026-10-01 09:00:00']);
        $payment = DB::table('payments')->insertGetId(['sale_id' => $sale, 'amount' => '10.10', 'payment_method' => 'gcash', 'paid_at' => '2026-10-23 23:59:59']);
        DB::table('payment_reversals')->insert(['payment_id' => $payment, 'user_id' => $user->id, 'reason' => 'Refund', 'reversed_at' => '2026-10-24 00:00:00']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sales.revenue', 0)->where('collections.gross', 0)->where('collections.reversals', 10.1)->where('collections.net', -10.1)
            ->where('collections.gcash', -10.1)->where('credit.outstanding', 10.1));
    }

    public function test_today_starts_at_midnight_and_ignores_future_and_invalid_financial_records(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $sale = DB::table('sales')->insertGetId(['sale_number' => 'MIDNIGHT', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-24 00:00:00']);
        DB::table('sales')->insert(['sale_number' => 'FUTURE', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '500.00', 'status' => 'completed', 'sold_at' => '2026-10-24 13:00:00']);
        $validPayment = DB::table('payments')->insertGetId(['sale_id' => $sale, 'amount' => '20.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 00:00:00']);
        $otherPayment = DB::table('payments')->insertGetId(['sale_id' => $sale, 'amount' => '10.00', 'payment_method' => 'gcash', 'paid_at' => '2026-10-24 10:00:00']);
        DB::table('payment_reversals')->insert([
            ['payment_id' => $validPayment, 'user_id' => $user->id, 'reason' => 'Future reversal', 'reversed_at' => '2026-10-24 13:00:00'],
            ['payment_id' => $otherPayment, 'user_id' => $user->id, 'reason' => 'Invalid early reversal', 'reversed_at' => '2026-10-24 09:00:00'],
        ]);
        DB::table('payments')->insert([
            ['sale_id' => $sale, 'amount' => '70.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 13:00:00'],
            ['sale_id' => $sale, 'amount' => '-50.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 10:00:00'],
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sales.sale_count', 1)->where('sales.revenue', 100)->where('collections.gross', 30)->where('collections.reversals', 0)
            ->where('collections.net', 30)->where('credit.outstanding', 70));
        $this->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales_revenue', 100)->where('summary.net_collections', 30)->where('outstandingCredit', 70));
    }

    public function test_legacy_overpayment_does_not_offset_other_outstanding_sales(): void
    {
        $user = User::factory()->create();
        $customer = DB::table('customers')->insertGetId(['name' => 'Legacy customer']);
        $overpaid = DB::table('sales')->insertGetId(['sale_number' => 'OVERPAID', 'customer_id' => $customer, 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '10.00', 'status' => 'completed', 'sold_at' => now()]);
        DB::table('sales')->insert(['sale_number' => 'UNPAID', 'customer_id' => $customer, 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => now()]);
        DB::table('payments')->insert(['sale_id' => $overpaid, 'amount' => '30.00', 'payment_method' => 'cash', 'paid_at' => now()]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('credit.outstanding', 100)->where('credit.sale_count', 1));
        $this->get(route('customers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('customers.data.0.outstanding_balance', 100));
        $this->get(route('customers.show', $customer))->assertOk()->assertInertia(fn (Assert $page) => $page->where('outstandingBalance', 100));
    }

    public function test_sales_overview_fills_missing_days_and_only_counts_completed_sales_in_the_last_seven_days(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        DB::table('sales')->insert([
            ['sale_number' => 'BEFORE', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '900.00', 'status' => 'completed', 'sold_at' => '2026-10-17 23:59:59'],
            ['sale_number' => 'START', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '10.10', 'status' => 'completed', 'sold_at' => '2026-10-18 00:00:00'],
            ['sale_number' => 'SAME-DAY', 'user_id' => $user->id, 'sale_type' => 'delivery', 'total_amount' => '20.20', 'status' => 'completed', 'sold_at' => '2026-10-18 12:00:00'],
            ['sale_number' => 'CANCELLED', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '999.00', 'status' => 'cancelled', 'sold_at' => '2026-10-19 12:00:00'],
            ['sale_number' => 'TODAY', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '15.15', 'status' => 'completed', 'sold_at' => '2026-10-24 12:00:00'],
            ['sale_number' => 'FUTURE', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '900.00', 'status' => 'completed', 'sold_at' => '2026-10-24 12:00:01'],
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('salesOverview', 7)->where('salesOverview.0.date', '2026-10-18')->where('salesOverview.0.revenue', 30.3)
            ->where('salesOverview.1.date', '2026-10-19')->where('salesOverview.1.revenue', 0)
            ->where('salesOverview.5.revenue', 0)->where('salesOverview.6.date', '2026-10-24')->where('salesOverview.6.revenue', 15.15));
    }

    public function test_top_products_and_recent_sales_are_bounded_and_do_not_duplicate_items_or_payments(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $customer = DB::table('customers')->insertGetId(['name' => 'Regular customer']);
        $products = [];
        $sales = [];
        for ($index = 1; $index <= 6; $index++) {
            $products[$index] = DB::table('products')->insertGetId(['name' => 'Product '.$index, 'price' => '100.00', 'is_active' => false]);
            $sales[$index] = DB::table('sales')->insertGetId([
                'sale_number' => 'SALE-'.$index, 'user_id' => $user->id, 'customer_id' => $customer, 'sale_type' => 'walk_in',
                'total_amount' => $index * 100, 'status' => 'completed', 'sold_at' => '2026-10-24 11:0'.$index.':00',
            ]);
            DB::table('sale_items')->insert(['sale_id' => $sales[$index], 'product_id' => $products[$index], 'quantity' => $index, 'unit_price' => '100.00', 'subtotal' => $index * 100]);
            DB::table('payments')->insert([
                ['sale_id' => $sales[$index], 'amount' => '20.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:10:00'],
                ['sale_id' => $sales[$index], 'amount' => '30.00', 'payment_method' => 'gcash', 'paid_at' => '2026-10-24 11:11:00'],
            ]);
        }
        $reversedPayment = DB::table('payments')->where('sale_id', $sales[6])->where('payment_method', 'cash')->value('id');
        DB::table('payment_reversals')->insert(['payment_id' => $reversedPayment, 'user_id' => $user->id, 'reason' => 'Correction', 'reversed_at' => '2026-10-24 11:30:00']);
        $cancelled = DB::table('sales')->insertGetId(['sale_number' => 'CANCELLED', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '999.00', 'status' => 'cancelled', 'sold_at' => '2026-10-24 11:07:00']);
        $future = DB::table('sales')->insertGetId(['sale_number' => 'FUTURE', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '999.00', 'status' => 'completed', 'sold_at' => '2026-10-24 13:00:00']);
        $outside = DB::table('sales')->insertGetId(['sale_number' => 'OLDER', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '999.00', 'status' => 'completed', 'sold_at' => '2026-10-17 23:59:59']);
        DB::table('sale_items')->insert([
            ['sale_id' => $cancelled, 'product_id' => $products[1], 'quantity' => 999, 'unit_price' => '1.00', 'subtotal' => '999.00'],
            ['sale_id' => $future, 'product_id' => $products[1], 'quantity' => 999, 'unit_price' => '1.00', 'subtotal' => '999.00'],
            ['sale_id' => $outside, 'product_id' => $products[1], 'quantity' => 999, 'unit_price' => '1.00', 'subtotal' => '999.00'],
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('topProducts', 4)->where('topProducts.0.id', $products[6])->where('topProducts.0.units_sold', 6)
            ->where('topProducts.3.id', $products[3])->where('topProducts.3.units_sold', 3)
            ->has('recentSales', 5)->where('recentSales.0.id', $cancelled)->where('recentSales.0.status', 'cancelled')->where('recentSales.0.customer_name', null)
            ->where('recentSales.1.id', $sales[6])->where('recentSales.1.amount_paid', 30)->where('recentSales.1.customer_name', 'Regular customer')
            ->where('recentSales.4.id', $sales[3])->where('recentSales.4.amount_paid', 50));
    }
}
