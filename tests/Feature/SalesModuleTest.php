<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SalesModuleTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', 'sales.index', []])]
    #[TestWith(['GET', 'sales.create', []])]
    #[TestWith(['POST', 'sales.store', []])]
    #[TestWith(['GET', 'sales.show', ['sale' => 1]])]
    /**
     * @param  array{sale?: int}  $parameters
     */
    public function test_guests_are_redirected_to_login(string $method, string $route, array $parameters): void
    {
        $this->call($method, route($route, $parameters))->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('sales');
    }

    public function test_create_form_lists_customers_and_active_products_with_a_fresh_submission_token(): void
    {
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana', 'address' => '12 Main Street']);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);
        DB::table('products')->insert(['name' => 'Inactive', 'price' => '99.00', 'is_active' => false]);

        $response = $this->actingAs(User::factory()->create())->get(route('sales.create'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Sales/Create')
            ->has('customers', 1)->where('customers.0.id', $customerId)->where('customers.0.address', '12 Main Street')
            ->has('products', 1)->where('products.0.id', $productId)->where('products.0.stock_quantity', null)
            ->where('todaySummary.sale_count', 0)->where('todaySummary.revenue', 0)->where('todaySummary.units_sold', 0));
        $token = $response->inertiaProps('submissionToken');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $token);
        $this->get(route('sales.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('submissionToken', fn (string $newToken): bool => $newToken !== $token));
    }

    public function test_pos_summary_counts_todays_completed_sales_and_units_without_multiplying_revenue(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $water = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.10']);
        $bottle = DB::table('products')->insertGetId(['name' => 'Bottled water', 'price' => '35.55']);
        $today = DB::table('sales')->insertGetId(['sale_number' => 'TODAY', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '85.75', 'status' => 'completed', 'sold_at' => '2026-10-24 00:00:00']);
        $unpaid = DB::table('sales')->insertGetId(['sale_number' => 'UNPAID', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '25.10', 'status' => 'completed', 'sold_at' => '2026-10-24 12:00:00']);
        $cancelled = DB::table('sales')->insertGetId(['sale_number' => 'CANCELLED', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '251.00', 'status' => 'cancelled', 'sold_at' => '2026-10-24 11:00:00']);
        $yesterday = DB::table('sales')->insertGetId(['sale_number' => 'YESTERDAY', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '251.00', 'status' => 'completed', 'sold_at' => '2026-10-23 23:59:59']);
        $future = DB::table('sales')->insertGetId(['sale_number' => 'FUTURE', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '251.00', 'status' => 'completed', 'sold_at' => '2026-10-24 12:00:01']);
        DB::table('sale_items')->insert([
            ['sale_id' => $today, 'product_id' => $water, 'quantity' => 2, 'unit_price' => '25.10', 'subtotal' => '50.20'],
            ['sale_id' => $today, 'product_id' => $bottle, 'quantity' => 1, 'unit_price' => '35.55', 'subtotal' => '35.55'],
            ['sale_id' => $unpaid, 'product_id' => $water, 'quantity' => 1, 'unit_price' => '25.10', 'subtotal' => '25.10'],
            ['sale_id' => $cancelled, 'product_id' => $water, 'quantity' => 10, 'unit_price' => '25.10', 'subtotal' => '251.00'],
            ['sale_id' => $yesterday, 'product_id' => $water, 'quantity' => 10, 'unit_price' => '25.10', 'subtotal' => '251.00'],
            ['sale_id' => $future, 'product_id' => $water, 'quantity' => 10, 'unit_price' => '25.10', 'subtotal' => '251.00'],
        ]);
        DB::table('payments')->insert([
            ['sale_id' => $today, 'amount' => '10.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 10:00:00'],
            ['sale_id' => $today, 'amount' => '15.00', 'payment_method' => 'gcash', 'paid_at' => '2026-10-24 11:00:00'],
        ]);

        $this->actingAs($user)->get(route('sales.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('todaySummary.sale_count', 2)->where('todaySummary.revenue', 110.85)->where('todaySummary.units_sold', 4)->where('timezone', 'UTC'));
    }

    public function test_walk_in_sale_saves_multiple_items_server_prices_and_exact_totals(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $refillId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.10']);
        $bottleId = DB::table('products')->insertGetId(['name' => 'Bottle', 'price' => '35.55', 'stock_quantity' => 8]);

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111',
            'sale_type' => 'walk_in', 'customer_id' => '', 'delivery_address' => 'Discard this address',
            'items' => [['product_id' => $refillId, 'quantity' => 3], ['product_id' => $bottleId, 'quantity' => 2]],
            'total_amount' => '0.01', 'sale_number' => 'SPOOFED', 'status' => 'cancelled', 'user_id' => $otherUser->id,
            'sold_at' => '2000-01-01 00:00:00',
        ]);

        $sale = DB::table('sales')->first();
        $response->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $sale->id)->assertSessionHas('status', 'Sale recorded.');
        $this->assertMatchesRegularExpression('/^SALE-[0-9A-HJKMNP-TV-Z]{26}$/', $sale->sale_number);
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id, 'sale_type' => 'walk_in', 'customer_id' => null, 'delivery_address' => null,
            'user_id' => $user->id, 'total_amount' => '146.40', 'status' => 'completed',
            'sold_at' => now()->toDateTimeString(), 'created_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_id' => $refillId, 'quantity' => 3, 'unit_price' => '25.10', 'subtotal' => '75.30']);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_id' => $bottleId, 'quantity' => 2, 'unit_price' => '35.55', 'subtotal' => '71.10']);
        $this->assertDatabaseHas('products', ['id' => $refillId, 'stock_quantity' => null]);
        $this->assertDatabaseHas('products', ['id' => $bottleId, 'stock_quantity' => 6]);
        $this->assertDatabaseEmpty('payments');
        $this->assertDatabaseEmpty('gallon_exchanges');
    }

    #[TestWith(['delivery', true])]
    #[TestWith(['delivery', false])]
    #[TestWith(['walk_in', true])]
    public function test_customer_selection_is_optional_for_both_sale_types(string $saleType, bool $selectCustomer): void
    {
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana', 'address' => 'Old address']);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => $saleType,
            'customer_id' => $selectCustomer ? $customerId : null, 'delivery_address' => 'New delivery address',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('sales', [
            'sale_type' => $saleType, 'customer_id' => $selectCustomer ? $customerId : null,
            'delivery_address' => $saleType === 'delivery' ? 'New delivery address' : null, 'total_amount' => '25.00',
        ]);
    }

    public function test_retried_submissions_return_the_same_receipt_without_changing_stock_or_prices(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 2]);
        $payload = [
            'submission_token' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 2]],
        ];
        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertSessionHasNoErrors();
        $saleId = DB::table('sales')->value('id');
        DB::table('products')->where('id', $productId)->update(['is_active' => false, 'price' => '99.00']);
        $payload['submission_token'] = strtoupper($payload['submission_token']);

        $this->post(route('sales.store'), $payload)->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $saleId);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total_amount' => '50.00']);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $saleId, 'unit_price' => '25.00', 'subtotal' => '50.00']);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 0]);
    }

    public function test_new_tokens_create_distinct_sale_numbers_for_identical_carts(): void
    {
        $this->freezeTime();
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);
        $this->actingAs(User::factory()->create());

        foreach (['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222'] as $token) {
            $this->post(route('sales.store'), [
                'submission_token' => $token, 'sale_type' => 'walk_in', 'items' => [['product_id' => $productId, 'quantity' => 1]],
            ])->assertSessionHasNoErrors()->assertRedirect();
        }

        $this->assertDatabaseCount('sales', 2);
        $this->assertSame(2, DB::table('sales')->distinct()->count('sale_number'));
    }

    public function test_submission_tokens_are_scoped_to_the_recording_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);
        $payload = [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ];
        $this->actingAs($firstUser)->post(route('sales.store'), $payload)->assertSessionHasNoErrors();

        $response = $this->actingAs($secondUser)->post(route('sales.store'), $payload);

        $response->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', DB::table('sales')->where('user_id', $secondUser->id)->value('id'));
        $this->assertDatabaseCount('sales', 2);
        $this->assertDatabaseHas('sales', ['user_id' => $firstUser->id]);
        $this->assertDatabaseHas('sales', ['user_id' => $secondUser->id]);
    }

    public function test_receipts_keep_the_server_price_at_sale_time_after_product_changes(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '20.00']);
        $this->actingAs(User::factory()->create())->get(route('sales.create'))->assertOk();
        DB::table('products')->where('id', $productId)->update(['price' => '25.25']);
        $this->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 3]],
        ])->assertSessionHasNoErrors();
        $saleId = DB::table('sales')->value('id');
        DB::table('products')->where('id', $productId)->update(['price' => '99.99', 'is_active' => false]);

        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Show')->where('sale.id', $saleId)->where('sale.total_amount', fn (int|float|string $amount): bool => (string) $amount === '75.75')
            ->where('items.0.product_name', 'Refill')->where('items.0.quantity', 3)
            ->where('items.0.unit_price', fn (int|float|string $price): bool => (string) $price === '25.25')
            ->where('items.0.subtotal', fn (int|float|string $amount): bool => (string) $amount === '75.75'));
    }

    public function test_missing_required_sale_fields_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('sales.store'), [])->assertSessionHasErrors([
            'submission_token' => 'The submission token field is required.',
            'sale_type' => 'The sale type field is required.', 'items' => 'The items field is required.',
        ]);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
    }

    #[TestWith(['submission_token', 'invalid', 'submission_token'])]
    #[TestWith(['sale_type', 'online', 'sale_type'])]
    #[TestWith(['customer_id', 999, 'customer_id'])]
    #[TestWith(['customer_id', ['invalid'], 'customer_id'])]
    #[TestWith(['items', [], 'items'])]
    #[TestWith(['items', 'invalid', 'items'])]
    #[TestWith(['items', ['named' => ['product_id' => 1, 'quantity' => 1]], 'items'])]
    #[TestWith(['items', [[]], 'items.0.product_id'])]
    #[TestWith(['items', [['product_id' => 999, 'quantity' => 1]], 'items.0.product_id'])]
    #[TestWith(['items', [['product_id' => 1, 'quantity' => 0]], 'items.0.quantity'])]
    #[TestWith(['items', [['product_id' => 1, 'quantity' => -1]], 'items.0.quantity'])]
    #[TestWith(['items', [['product_id' => 1, 'quantity' => 1.5]], 'items.0.quantity'])]
    #[TestWith(['items', [['product_id' => 1, 'quantity' => 4294967296]], 'items.0.quantity'])]
    #[TestWith(['items', [['product_id' => 1, 'quantity' => 1, 'unit_price' => '0.01']], 'items.0'])]
    #[TestWith(['items', [['product_id' => 1, 'quantity' => 1], ['product_id' => '1', 'quantity' => 2]], 'items.0.product_id'])]
    public function test_invalid_payloads_leave_sales_and_stock_unchanged(string $field, mixed $value, string $error): void
    {
        DB::table('products')->insert(['id' => 1, 'name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);
        $payload = [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ];
        $payload[$field] = $value;

        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertSessionHasErrors([$error]);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
        $this->assertDatabaseHas('products', ['id' => 1, 'stock_quantity' => 10]);
    }

    #[TestWith([null])]
    #[TestWith(['   '])]
    #[TestWith([123])]
    public function test_deliveries_require_a_nonempty_text_address(mixed $address): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'delivery',
            'delivery_address' => $address, 'items' => [['product_id' => $productId, 'quantity' => 1]],
        ])->assertSessionHasErrors(['delivery_address']);

        $this->assertDatabaseEmpty('sales');
    }

    public function test_delivery_addresses_cannot_exceed_the_input_limit(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'delivery',
            'delivery_address' => str_repeat('a', 10001), 'items' => [['product_id' => $productId, 'quantity' => 1]],
        ])->assertSessionHasErrors(['delivery_address' => 'The delivery address field must not be greater than 10000 characters.']);

        $this->assertDatabaseEmpty('sales');
    }

    public function test_sales_reject_more_than_one_hundred_items(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => array_fill(0, 101, ['product_id' => $productId, 'quantity' => 1]),
        ])->assertSessionHasErrors(['items' => 'The items field must not have more than 100 items.']);

        $this->assertDatabaseEmpty('sales');
    }

    public function test_inactive_products_cannot_be_sold(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'is_active' => false]);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ])->assertSessionHasErrors(['items.0.product_id' => 'Select an active product.']);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
    }

    public function test_insufficient_stock_rejects_the_entire_cart(): void
    {
        $firstProduct = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);
        $secondProduct = DB::table('products')->insertGetId(['name' => 'Bottle', 'price' => '50.00', 'stock_quantity' => 1]);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $firstProduct, 'quantity' => 2], ['product_id' => $secondProduct, 'quantity' => 2]],
        ])->assertSessionHasErrors(['items.1.quantity' => 'There is not enough stock for this product.']);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
        $this->assertDatabaseHas('products', ['id' => $firstProduct, 'stock_quantity' => 10]);
        $this->assertDatabaseHas('products', ['id' => $secondProduct, 'stock_quantity' => 1]);
    }

    #[TestWith([2])]
    #[TestWith([4294967295])]
    public function test_totals_exceeding_storage_limits_are_rejected_before_multiplication(int $quantity): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '99999999.99']);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => $quantity]],
        ])->assertSessionHasErrors(['items' => 'The sale total must not exceed 99,999,999.99.']);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
    }

    public function test_the_combined_total_cannot_overflow_even_when_each_line_fits(): void
    {
        $firstProduct = DB::table('products')->insertGetId(['name' => 'First', 'price' => '50000000.00']);
        $secondProduct = DB::table('products')->insertGetId(['name' => 'Second', 'price' => '50000000.00']);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $firstProduct, 'quantity' => 1], ['product_id' => $secondProduct, 'quantity' => 1]],
        ])->assertSessionHasErrors(['items' => 'The sale total must not exceed 99,999,999.99.']);

        $this->assertDatabaseEmpty('sales');
    }

    #[TestWith(['0.00', 4294967295, '0.00'])]
    #[TestWith(['99999999.99', 1, '99999999.99'])]
    #[TestWith(['0.01', 3, '0.03'])]
    public function test_valid_price_and_quantity_boundaries_are_saved_exactly(string $price, int $quantity, string $total): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => $price]);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => $quantity]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('sales', ['total_amount' => $total]);
        $this->assertDatabaseHas('sale_items', ['quantity' => $quantity, 'unit_price' => $price, 'subtotal' => $total]);
    }

    public function test_database_failure_rolls_back_items_stock_and_token_and_allows_retry(): void
    {
        Exceptions::fake([QueryException::class]);
        $firstProduct = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);
        $secondProduct = DB::table('products')->insertGetId(['name' => 'Bottle', 'price' => '50.00', 'stock_quantity' => 10]);
        $payload = [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $firstProduct, 'quantity' => 1], ['product_id' => $secondProduct, 'quantity' => 2]],
        ];
        DB::statement("CREATE TRIGGER fail_second_item BEFORE INSERT ON sale_items WHEN NEW.quantity = 2 BEGIN SELECT RAISE(ABORT, 'Simulated item failure'); END");

        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertServerError();

        Exceptions::assertReported(QueryException::class);
        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
        $this->assertDatabaseHas('products', ['id' => $firstProduct, 'stock_quantity' => 10]);
        $this->assertDatabaseHas('products', ['id' => $secondProduct, 'stock_quantity' => 10]);

        DB::statement('DROP TRIGGER fail_second_item');
        $this->post(route('sales.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseHas('products', ['id' => $firstProduct, 'stock_quantity' => 9]);
        $this->assertDatabaseHas('products', ['id' => $secondProduct, 'stock_quantity' => 8]);
    }

    public function test_history_is_paginated_newest_first_and_preserves_date_filters(): void
    {
        $user = User::factory()->create();
        $customer = DB::table('customers')->insertGetId(['name' => 'Ana']);
        for ($number = 1; $number <= 12; $number++) {
            DB::table('sales')->insert([
                'sale_number' => 'SALE-'.$number, 'user_id' => $user->id, 'customer_id' => $customer,
                'sale_type' => 'delivery', 'delivery_address' => '12 Main Street', 'total_amount' => '25.00',
                'status' => 'completed', 'sold_at' => '2026-10-24 12:00:00',
            ]);
        }

        $response = $this->actingAs($user)->get(route('sales.index', ['date_from' => '2026-10-24', 'date_to' => '2026-10-24', 'per_page' => 10]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Sales/Index')
            ->has('sales.data', 10)->where('sales.total', 12)->where('sales.data.0.sale_number', 'SALE-12')
            ->where('sales.data.0.customer_name', 'Ana')->where('filters.date_from', '2026-10-24')->where('filters.date_to', '2026-10-24'));
        $this->assertStringContainsString('date_from=2026-10-24', $response->inertiaProps('sales.next_page_url'));
        $this->assertStringContainsString('date_to=2026-10-24', $response->inertiaProps('sales.next_page_url'));
        $this->assertStringContainsString('per_page=10', $response->inertiaProps('sales.next_page_url'));
        $this->get($response->inertiaProps('sales.next_page_url'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('sales.data', 2)->where('sales.data.0.sale_number', 'SALE-2'));
    }

    #[TestWith(['2026-10-24', '2026-10-24', 2])]
    #[TestWith(['2026-10-24', '', 3])]
    #[TestWith(['', '2026-10-24', 3])]
    #[TestWith(['', '', 4])]
    public function test_date_filters_are_inclusive_and_allow_one_sided_ranges(string $dateFrom, string $dateTo, int $count): void
    {
        $user = User::factory()->create();
        foreach (['2026-10-23 23:59:59', '2026-10-24 00:00:00', '2026-10-24 23:59:59', '2026-10-25 00:00:00'] as $index => $soldAt) {
            DB::table('sales')->insert([
                'sale_number' => 'SALE-'.$index, 'user_id' => $user->id, 'sale_type' => 'walk_in',
                'total_amount' => '25.00', 'status' => 'completed', 'sold_at' => $soldAt,
            ]);
        }

        $this->actingAs($user)->get(route('sales.index', ['date_from' => $dateFrom, 'date_to' => $dateTo]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('sales.data', $count)
            ->where('sales.total', $count)->where('sales.data.0.customer_name', null));
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

        $this->actingAs(User::factory()->create())->get(route('sales.index', $filters))->assertSessionHasErrors([$field]);
    }

    #[TestWith(['999'])]
    #[TestWith(['invalid'])]
    public function test_missing_or_invalid_sale_identifiers_return_404(string $sale): void
    {
        $this->actingAs(User::factory()->create())->get(route('sales.show', $sale))->assertNotFound();
    }

    public function test_receipt_names_and_addresses_are_kept_as_escaped_data(): void
    {
        $unsafeText = '<script>alert("receipt")</script>';
        $user = User::factory()->create(['name' => $unsafeText]);
        $customerId = DB::table('customers')->insertGetId(['name' => $unsafeText]);
        $productId = DB::table('products')->insertGetId(['name' => $unsafeText, 'price' => '25.00']);
        $this->actingAs($user)->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'delivery',
            'customer_id' => $customerId, 'delivery_address' => $unsafeText, 'items' => [['product_id' => $productId, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->get(route('sales.show', DB::table('sales')->value('id')))->assertOk()->assertDontSee($unsafeText, false)
            ->assertInertia(fn (Assert $page) => $page->where('sale.customer_name', $unsafeText)
                ->where('sale.recorded_by', $unsafeText)->where('sale.delivery_address', $unsafeText)
                ->where('items.0.product_name', $unsafeText)->missing('sale.submission_token'));
    }
}
