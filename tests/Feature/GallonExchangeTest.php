<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GallonExchangeTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', 'gallon-exchanges.index', []])]
    #[TestWith(['PUT', 'sales.gallon-exchange.update', ['sale' => 1]])]
    /**
     * @param  array{sale?: int}  $parameters
     */
    public function test_guests_cannot_view_or_record_gallon_exchanges(string $method, string $route, array $parameters): void
    {
        $this->call($method, route($route, $parameters), ['filled_gallons_out' => 2, 'empty_gallons_returned' => 1])->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('gallon_exchanges');
    }

    #[TestWith(['walk_in', false])]
    #[TestWith(['delivery', true])]
    public function test_applicable_sales_record_actual_gallon_counts_separately_from_items_and_payments(string $saleType, bool $selectCustomer): void
    {
        $this->freezeTime();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => $saleType,
            'customer_id' => $selectCustomer ? $customerId : null, 'delivery_address' => '12 Main Street',
            'items' => [['product_id' => $productId, 'quantity' => 2]], 'initial_payment_type' => 'partial',
            'initial_payment_amount' => '20.00', 'payment_method' => 'cash',
            'track_gallon_exchange' => true, 'filled_gallons_out' => 3, 'empty_gallons_returned' => 9,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $saleId = DB::table('sales')->value('id');
        $this->assertDatabaseHas('gallon_exchanges', [
            'sale_id' => $saleId, 'filled_gallons_out' => 3, 'empty_gallons_returned' => 9,
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total_amount' => '50.00']);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $saleId, 'quantity' => 2, 'unit_price' => '25.00', 'subtotal' => '50.00']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => '20.00']);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 8]);
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('gallonExchange.filled_gallons_out', 3)->where('gallonExchange.empty_gallons_returned', 9)
            ->where('sale.total_amount', 50)->where('sale.cash_received', 20)->where('sale.outstanding_balance', 30));
    }

    #[TestWith([false])]
    #[TestWith([null])]
    public function test_sales_without_gallon_tracking_do_not_create_exchange_records(?bool $trackGallons): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Bottle', 'price' => '10.00']);
        $payload = [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 1]], 'filled_gallons_out' => -1, 'empty_gallons_returned' => 'ignored',
        ];
        if ($trackGallons !== null) {
            $payload['track_gallon_exchange'] = $trackGallons;
        }

        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseEmpty('gallon_exchanges');
        $this->assertDatabaseEmpty('payments');
        $this->get(route('sales.show', DB::table('sales')->value('id')))->assertOk()->assertInertia(fn (Assert $page) => $page->where('gallonExchange', null));
    }

    public function test_invalid_gallon_counts_at_checkout_leave_no_sale_stock_or_payment_changes(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 1]], 'initial_payment_type' => 'full', 'payment_method' => 'cash',
            'track_gallon_exchange' => true, 'filled_gallons_out' => -1, 'empty_gallons_returned' => 1.5,
        ])->assertSessionHasErrors([
            'filled_gallons_out' => 'The filled gallons out field must be at least 0.',
            'empty_gallons_returned' => 'The empty gallons returned field must be an integer.',
        ]);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('gallon_exchanges');
        $this->assertDatabaseEmpty('sale_items');
        $this->assertDatabaseEmpty('payments');
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 10]);
    }

    #[TestWith(['yes', 1, 1, 'track_gallon_exchange'])]
    #[TestWith([true, null, null, 'filled_gallons_out'])]
    #[TestWith([true, 1, null, 'empty_gallons_returned'])]
    #[TestWith([true, 4294967296, 0, 'filled_gallons_out'])]
    #[TestWith([true, 0, 4294967296, 'empty_gallons_returned'])]
    public function test_checkout_validates_the_tracking_flag_and_conditional_quantities(mixed $track, mixed $filled, mixed $returned, string $error): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
            'track_gallon_exchange' => $track, 'filled_gallons_out' => $filled, 'empty_gallons_returned' => $returned,
        ])->assertSessionHasErrors([$error]);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('gallon_exchanges');
    }

    #[TestWith([0, 0])]
    #[TestWith([4294967295, 4294967295])]
    #[TestWith([0, 12])]
    #[TestWith([5, 0])]
    public function test_gallon_totals_accept_nonnegative_integer_boundaries_and_independent_return_counts(int $filled, int $returned): void
    {
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '50.00',
            'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);

        $this->actingAs($user)->put(route('sales.gallon-exchange.update', $saleId), ['filled_gallons_out' => $filled, 'empty_gallons_returned' => $returned])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $saleId)->assertSessionHas('status', 'Gallon totals saved.');

        $this->assertDatabaseHas('gallon_exchanges', ['sale_id' => $saleId, 'filled_gallons_out' => $filled, 'empty_gallons_returned' => $returned]);
        $this->assertDatabaseEmpty('payments');
    }

    public function test_receipt_updates_replace_totals_preserve_creation_time_and_do_not_change_money_or_stock(): void
    {
        $this->freezeTime();
        $createdAt = now()->toDateTimeString();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);
        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in', 'customer_id' => $customerId,
            'items' => [['product_id' => $productId, 'quantity' => 2]], 'initial_payment_type' => 'partial',
            'initial_payment_amount' => '20.00', 'payment_method' => 'gcash',
            'track_gallon_exchange' => true, 'filled_gallons_out' => 2, 'empty_gallons_returned' => 1,
        ])->assertSessionHasNoErrors();
        $saleId = DB::table('sales')->value('id');
        $exchangeId = DB::table('gallon_exchanges')->value('id');
        $this->travel(1)->hours();
        $payload = ['filled_gallons_out' => 5, 'empty_gallons_returned' => 9, 'sale_id' => 999, 'id' => 999, 'amount' => '999.00', 'payment_method' => 'cash', 'total_amount' => 0];

        $this->put(route('sales.gallon-exchange.update', $saleId), $payload)->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $saleId);
        $this->put(route('sales.gallon-exchange.update', $saleId), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('gallon_exchanges', 1);
        $this->assertDatabaseHas('gallon_exchanges', ['id' => $exchangeId, 'sale_id' => $saleId, 'filled_gallons_out' => 5, 'empty_gallons_returned' => 9, 'created_at' => $createdAt, 'updated_at' => now()->toDateTimeString()]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total_amount' => '50.00']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => '20.00', 'payment_method' => 'gcash']);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 8]);
        $this->get(route('customers.show', $customerId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('outstandingBalance', 30));
        $this->get(route('gallon-exchanges.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.filled_gallons_out', 5)->where('summary.empty_gallons_returned', 9)->where('summary.net_gallons_out', -4));
    }

    public function test_gallon_fields_are_required_when_recording_totals(): void
    {
        $this->actingAs(User::factory()->create())->put(route('sales.gallon-exchange.update', 1), [])->assertSessionHasErrors([
            'filled_gallons_out' => 'The filled gallons out field is required.',
            'empty_gallons_returned' => 'The empty gallons returned field is required.',
        ]);

        $this->assertDatabaseEmpty('gallon_exchanges');
    }

    #[TestWith(['filled_gallons_out', -1, 'The filled gallons out field must be at least 0.'])]
    #[TestWith(['empty_gallons_returned', -1, 'The empty gallons returned field must be at least 0.'])]
    #[TestWith(['filled_gallons_out', 1.5, 'The filled gallons out field must be an integer.'])]
    #[TestWith(['empty_gallons_returned', 1.5, 'The empty gallons returned field must be an integer.'])]
    #[TestWith(['filled_gallons_out', 'invalid', 'The filled gallons out field must be an integer.'])]
    #[TestWith(['empty_gallons_returned', ['invalid'], 'The empty gallons returned field must be an integer.'])]
    #[TestWith(['filled_gallons_out', 4294967296, 'The filled gallons out field must not be greater than 4294967295.'])]
    #[TestWith(['empty_gallons_returned', 4294967296, 'The empty gallons returned field must not be greater than 4294967295.'])]
    public function test_invalid_updates_preserve_the_existing_exchange(string $field, mixed $value, string $message): void
    {
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '50.00',
            'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        DB::table('gallon_exchanges')->insert(['sale_id' => $saleId, 'filled_gallons_out' => 2, 'empty_gallons_returned' => 1]);
        $payload = ['filled_gallons_out' => 3, 'empty_gallons_returned' => 3];
        $payload[$field] = $value;

        $this->actingAs($user)->put(route('sales.gallon-exchange.update', $saleId), $payload)->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('gallon_exchanges', 1);
        $this->assertDatabaseHas('gallon_exchanges', ['sale_id' => $saleId, 'filled_gallons_out' => 2, 'empty_gallons_returned' => 1]);
        $this->assertDatabaseEmpty('payments');
    }

    public function test_retrying_checkout_does_not_duplicate_gallons_or_money(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);
        $payload = [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 2]], 'initial_payment_type' => 'full', 'payment_method' => 'cash',
            'track_gallon_exchange' => true, 'filled_gallons_out' => 2, 'empty_gallons_returned' => 1,
        ];
        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertSessionHasNoErrors();

        $this->post(route('sales.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('gallon_exchanges', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('gallon_exchanges', ['filled_gallons_out' => 2, 'empty_gallons_returned' => 1]);
    }

    public function test_gallon_insert_failure_rolls_back_the_sale_items_stock_payment_and_token(): void
    {
        Exceptions::fake([QueryException::class]);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);
        $payload = [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 2]], 'initial_payment_type' => 'full', 'payment_method' => 'cash',
            'track_gallon_exchange' => true, 'filled_gallons_out' => 2, 'empty_gallons_returned' => 1,
        ];
        DB::statement("CREATE TRIGGER fail_exchange BEFORE INSERT ON gallon_exchanges BEGIN SELECT RAISE(ABORT, 'Simulated gallon failure'); END");

        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertServerError();

        Exceptions::assertReported(QueryException::class);
        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
        $this->assertDatabaseEmpty('payments');
        $this->assertDatabaseEmpty('gallon_exchanges');
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 10]);

        DB::statement('DROP TRIGGER fail_exchange');
        $this->post(route('sales.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('gallon_exchanges', 1);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 8]);
    }

    public function test_cancellation_preserves_gallon_history_blocks_edits_and_removes_only_summary_contributions(): void
    {
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '50.00',
            'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        DB::table('gallon_exchanges')->insert(['sale_id' => $saleId, 'filled_gallons_out' => 5, 'empty_gallons_returned' => 2]);
        DB::table('payments')->insert(['sale_id' => $saleId, 'amount' => '20.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 10:00:00']);
        $this->actingAs($user)->get(route('gallon-exchanges.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.filled_gallons_out', 5));
        DB::table('sales')->where('id', $saleId)->update(['status' => 'cancelled']);

        $this->put(route('sales.gallon-exchange.update', $saleId), ['filled_gallons_out' => 0, 'empty_gallons_returned' => 5])
            ->assertSessionHasErrors(['gallon_exchange' => 'Gallon records for cancelled sales are read-only.']);

        $this->assertDatabaseCount('gallon_exchanges', 1);
        $this->assertDatabaseHas('gallon_exchanges', ['sale_id' => $saleId, 'filled_gallons_out' => 5, 'empty_gallons_returned' => 2]);
        $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => '20.00']);
        $this->get(route('gallon-exchanges.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('exchanges.data', 1)->where('exchanges.data.0.status', 'cancelled')->where('summary.sale_count', 0)
            ->where('summary.filled_gallons_out', 0)->where('summary.empty_gallons_returned', 0)->where('summary.net_gallons_out', 0));
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('gallonExchange.filled_gallons_out', 5)->where('gallonExchange.empty_gallons_returned', 2));
    }

    public function test_cancelled_sales_without_an_exchange_cannot_get_new_gallon_records(): void
    {
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '50.00',
            'status' => 'cancelled', 'sold_at' => '2026-10-24 10:00:00',
        ]);

        $this->actingAs($user)->put(route('sales.gallon-exchange.update', $saleId), ['filled_gallons_out' => 2, 'empty_gallons_returned' => 1])->assertSessionHasErrors(['gallon_exchange']);

        $this->assertDatabaseEmpty('gallon_exchanges');
    }

    public function test_history_is_paginated_and_summary_uses_all_matching_completed_sales(): void
    {
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        for ($number = 1; $number <= 13; $number++) {
            $saleId = DB::table('sales')->insertGetId([
                'sale_number' => 'SALE-'.$number, 'user_id' => $user->id, 'customer_id' => $number === 13 ? $customerId : null,
                'sale_type' => 'walk_in', 'total_amount' => '50.00', 'status' => $number === 13 ? 'cancelled' : 'completed', 'sold_at' => '2026-10-24 10:00:00',
            ]);
            DB::table('gallon_exchanges')->insert(['sale_id' => $saleId, 'filled_gallons_out' => 2, 'empty_gallons_returned' => 1]);
        }

        $response = $this->actingAs($user)->get(route('gallon-exchanges.index', ['date_from' => '2026-10-24', 'date_to' => '2026-10-24', 'per_page' => 10]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('GallonExchanges/Index')
            ->has('exchanges.data', 10)->where('exchanges.total', 13)->where('exchanges.data.0.sale_number', 'SALE-13')
            ->where('exchanges.data.0.customer_name', 'Ana')->where('exchanges.data.1.customer_name', null)
            ->where('summary.sale_count', 12)->where('summary.filled_gallons_out', 24)->where('summary.empty_gallons_returned', 12)->where('summary.net_gallons_out', 12));
        $this->assertStringContainsString('date_from=2026-10-24', $response->inertiaProps('exchanges.next_page_url'));
        $this->assertStringContainsString('date_to=2026-10-24', $response->inertiaProps('exchanges.next_page_url'));
        $this->assertStringContainsString('per_page=10', $response->inertiaProps('exchanges.next_page_url'));
        $this->get($response->inertiaProps('exchanges.next_page_url'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('exchanges.data', 3)->where('exchanges.data.0.sale_number', 'SALE-3')->where('summary.filled_gallons_out', 24));
    }

    #[TestWith(['2026-10-24', '2026-10-24', 2])]
    #[TestWith(['2026-10-24', '', 3])]
    #[TestWith(['', '2026-10-24', 3])]
    #[TestWith(['', '', 4])]
    public function test_date_filters_are_inclusive_and_apply_to_history_and_summary(string $dateFrom, string $dateTo, int $count): void
    {
        $user = User::factory()->create();
        foreach (['2026-10-23 23:59:59', '2026-10-24 00:00:00', '2026-10-24 23:59:59', '2026-10-25 00:00:00'] as $index => $soldAt) {
            $saleId = DB::table('sales')->insertGetId([
                'sale_number' => 'SALE-'.$index, 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '50.00',
                'status' => 'completed', 'sold_at' => $soldAt,
            ]);
            DB::table('gallon_exchanges')->insert(['sale_id' => $saleId, 'filled_gallons_out' => 2, 'empty_gallons_returned' => 1]);
        }

        $this->actingAs($user)->get(route('gallon-exchanges.index', ['date_from' => $dateFrom, 'date_to' => $dateTo]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('exchanges.data', $count)->where('summary.sale_count', $count)
                ->where('summary.filled_gallons_out', $count * 2)->where('summary.empty_gallons_returned', $count));
    }

    #[TestWith(['date_from', 'invalid'])]
    #[TestWith(['date_from', '2026-02-30'])]
    #[TestWith(['date_to', "2026-10-24' OR 1=1 --"])]
    #[TestWith(['date_to', '2026-10-23'])]
    #[TestWith(['per_page', 999])]
    #[TestWith(['page', 0])]
    public function test_history_rejects_invalid_filters(string $field, mixed $value): void
    {
        $filters = ['date_from' => '2026-10-24', 'date_to' => '2026-10-24'];
        $filters[$field] = $value;

        $this->actingAs(User::factory()->create())->get(route('gallon-exchanges.index', $filters))->assertSessionHasErrors([$field]);
    }

    public function test_empty_history_has_zero_summary_totals(): void
    {
        $this->actingAs(User::factory()->create())->get(route('gallon-exchanges.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('exchanges.data', 0)->where('summary.sale_count', 0)->where('summary.filled_gallons_out', 0)
            ->where('summary.empty_gallons_returned', 0)->where('summary.net_gallons_out', 0));
    }

    #[TestWith(['999'])]
    #[TestWith(['invalid'])]
    public function test_missing_or_invalid_sale_identifiers_return_404(string $sale): void
    {
        $this->actingAs(User::factory()->create())->put(route('sales.gallon-exchange.update', $sale), ['filled_gallons_out' => 2, 'empty_gallons_returned' => 1])->assertNotFound();

        $this->assertDatabaseEmpty('gallon_exchanges');
    }

    public function test_customer_names_in_history_are_kept_as_escaped_data(): void
    {
        $name = '<script>alert("gallons")</script>';
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => $name]);
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'customer_id' => $customerId, 'sale_type' => 'walk_in', 'total_amount' => '50.00',
            'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        DB::table('gallon_exchanges')->insert(['sale_id' => $saleId, 'filled_gallons_out' => 2, 'empty_gallons_returned' => 1]);

        $this->actingAs($user)->get(route('gallon-exchanges.index'))->assertOk()->assertDontSee($name, false)
            ->assertInertia(fn (Assert $page) => $page->where('exchanges.data.0.customer_name', $name));
    }
}
