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

class PaymentCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_record_payments(): void
    {
        $this->post(route('sales.payments.store', 1), [])->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('payments');
    }

    #[TestWith(['full', 'cash', '50.50', '0.00'])]
    #[TestWith(['full', 'gcash', '50.50', '0.00'])]
    #[TestWith(['partial', 'cash', '15.15', '35.35'])]
    #[TestWith(['partial', 'gcash', '15.15', '35.35'])]
    #[TestWith(['none', 'cash', '0.00', '50.50'])]
    public function test_sales_support_full_partial_and_no_initial_payment(string $type, string $method, string $paid, string $outstanding): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.25']);

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in', 'customer_id' => $customerId,
            'items' => [['product_id' => $productId, 'quantity' => 2]], 'initial_payment_type' => $type,
            'initial_payment_amount' => '15.15', 'payment_method' => $method, 'total_amount' => '0.01',
        ]);

        $saleId = DB::table('sales')->value('id');
        $response->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $saleId);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total_amount' => '50.50', 'status' => 'completed']);
        $this->assertDatabaseCount('payments', $type === 'none' ? 0 : 1);
        if ($type !== 'none') {
            $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => $paid, 'payment_method' => $method, 'user_id' => $user->id, 'paid_at' => now()->toDateTimeString()]);
        }
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sale.amount_paid', fn (int|float|string $value): bool => (float) $value === (float) $paid)
            ->where('sale.outstanding_balance', fn (int|float|string $value): bool => (float) $value === (float) $outstanding)
            ->where('sale.cash_received', fn (int|float|string $value): bool => (float) $value === ($method === 'cash' ? (float) $paid : 0.0))
            ->where('sale.gcash_received', fn (int|float|string $value): bool => (float) $value === ($method === 'gcash' ? (float) $paid : 0.0)));
        $this->get(route('customers.show', $customerId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('outstandingBalance', fn (int|float|string $value): bool => (float) $value === (float) $outstanding));
    }

    public function test_an_unpaid_credit_sale_is_not_cash_received(): void
    {
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '100.00']);
        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in', 'customer_id' => $customerId,
            'items' => [['product_id' => $productId, 'quantity' => 1]], 'initial_payment_type' => 'none',
            'initial_payment_amount' => '100.00', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->get(route('sales.show', DB::table('sales')->value('id')))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('payments', 0)->where('sale.amount_paid', 0)->where('sale.cash_received', 0)->where('sale.gcash_received', 0)
            ->where('sale.outstanding_balance', 100));

        $this->assertDatabaseEmpty('payments');
    }

    public function test_a_partial_sale_can_be_fully_settled_by_a_later_payment(): void
    {
        $this->freezeTime();
        $seller = User::factory()->create();
        $collector = User::factory()->create(['name' => 'Collector']);
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '100.00']);
        $this->actingAs($seller)->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in', 'customer_id' => $customerId,
            'items' => [['product_id' => $productId, 'quantity' => 1]], 'initial_payment_type' => 'partial',
            'initial_payment_amount' => '35.25', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $saleId = DB::table('sales')->value('id');

        $this->actingAs($collector)->post(route('sales.payments.store', $saleId), [
            'submission_token' => '22222222-2222-4222-8222-222222222222', 'amount' => '64.75', 'payment_method' => 'gcash',
            'user_id' => $seller->id, 'sale_id' => 999, 'paid_at' => '2000-01-01 00:00:00', 'balance' => 999,
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $saleId)->assertSessionHas('status', 'Payment recorded.');

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => '64.75', 'payment_method' => 'gcash', 'user_id' => $collector->id, 'paid_at' => now()->toDateTimeString()]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total_amount' => '100.00', 'status' => 'completed']);
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sale.amount_paid', 100)->where('sale.outstanding_balance', 0)->where('sale.cash_received', 35.25)
            ->where('sale.gcash_received', 64.75)->has('payments', 2)->where('payments.0.recorded_by', 'Collector'));
        $this->get(route('customers.show', $customerId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('outstandingBalance', 0)->where('sales.data.0.outstanding_balance', 0)->has('payments.data', 2));
        $this->get(route('customers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('customers.data.0.outstanding_balance', 0));
        $this->get(route('sales.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sales.data.0.amount_paid', 100)->where('sales.data.0.outstanding_balance', 0));
    }

    public function test_decimal_payments_settle_exactly_and_fully_paid_sales_reject_further_payments(): void
    {
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '0.30',
            'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        $this->actingAs($user)->post(route('sales.payments.store', $saleId), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'amount' => '0.10', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->post(route('sales.payments.store', $saleId), [
            'submission_token' => '22222222-2222-4222-8222-222222222222', 'amount' => '0.20', 'payment_method' => 'gcash',
        ])->assertSessionHasNoErrors();

        $this->post(route('sales.payments.store', $saleId), [
            'submission_token' => '33333333-3333-4333-8333-333333333333', 'amount' => '0.01', 'payment_method' => 'cash',
        ])->assertSessionHasErrors(['amount' => 'The payment amount must not exceed the outstanding balance.']);

        $this->assertDatabaseCount('payments', 2);
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sale.amount_paid', 0.3)->where('sale.outstanding_balance', 0)->where('sale.cash_received', 0.1)->where('sale.gcash_received', 0.2));
    }

    public function test_initial_payment_retries_do_not_duplicate_sales_payments_or_stock_deductions(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 2]);
        $payload = [
            'submission_token' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 2]], 'initial_payment_type' => 'full', 'payment_method' => 'cash',
        ];
        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertSessionHasNoErrors();
        $saleId = DB::table('sales')->value('id');
        $payload['submission_token'] = strtoupper($payload['submission_token']);

        $this->post(route('sales.store'), $payload)->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $saleId);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => '50.00']);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 0]);
    }

    public function test_payment_retries_from_another_staff_member_return_the_existing_payment_even_after_settlement(): void
    {
        $user = User::factory()->create();
        $secondUser = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00',
            'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        $payload = ['submission_token' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'amount' => '100.00', 'payment_method' => 'cash'];
        $this->actingAs($user)->post(route('sales.payments.store', $saleId), $payload)->assertSessionHasNoErrors();
        $payload['submission_token'] = strtoupper($payload['submission_token']);

        $this->actingAs($secondUser)->post(route('sales.payments.store', $saleId), $payload)
            ->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $saleId)->assertSessionHas('status', 'Payment already recorded.');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => '100.00', 'user_id' => $user->id]);
    }

    public function test_stale_payment_amounts_cannot_overpay_after_another_payment_is_recorded(): void
    {
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00',
            'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        $this->actingAs($user)->get(route('sales.show', $saleId))->assertOk();
        $this->post(route('sales.payments.store', $saleId), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'amount' => '60.00', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create())->post(route('sales.payments.store', $saleId), [
            'submission_token' => '22222222-2222-4222-8222-222222222222', 'amount' => '50.00', 'payment_method' => 'gcash',
        ])->assertSessionHasErrors(['amount' => 'The payment amount must not exceed the outstanding balance.']);

        $this->assertDatabaseCount('payments', 1);
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sale.outstanding_balance', 40));
    }

    public function test_overpayment_at_sale_creation_leaves_no_sale_items_payments_or_stock_changes(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 1]], 'initial_payment_type' => 'partial',
            'initial_payment_amount' => '25.01', 'payment_method' => 'cash', 'total_amount' => '999.00',
        ])->assertSessionHasErrors(['initial_payment_amount' => 'The initial payment must not exceed the sale total.']);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
        $this->assertDatabaseEmpty('payments');
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 10]);
    }

    public function test_payment_fields_are_required(): void
    {
        $this->actingAs(User::factory()->create())->post(route('sales.payments.store', 1), [])->assertSessionHasErrors([
            'submission_token' => 'The submission token field is required.', 'amount' => 'The amount field is required.',
            'payment_method' => 'The payment method field is required.',
        ]);

        $this->assertDatabaseEmpty('payments');
    }

    #[TestWith(['amount', 0, 'The amount field must be at least 0.01.'])]
    #[TestWith(['amount', '-1.00', 'The amount field must be at least 0.01.'])]
    #[TestWith(['amount', '1.001', 'The amount field must have 0-2 decimal places.'])]
    #[TestWith(['amount', '1e1', 'The amount field must have 0-2 decimal places.'])]
    #[TestWith(['amount', 'invalid', 'The amount field must be a number.'])]
    #[TestWith(['amount', '100000000.00', 'The amount field must not be greater than 99999999.99.'])]
    #[TestWith(['payment_method', 'credit', 'The selected payment method is invalid.'])]
    #[TestWith(['submission_token', 'invalid', 'The submission token field must be a valid UUID.'])]
    public function test_invalid_payment_inputs_are_rejected(string $field, mixed $value, string $message): void
    {
        $payload = ['submission_token' => '11111111-1111-4111-8111-111111111111', 'amount' => '1.00', 'payment_method' => 'cash'];
        $payload[$field] = $value;

        $this->actingAs(User::factory()->create())->post(route('sales.payments.store', 1), $payload)->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseEmpty('payments');
    }

    #[TestWith(['initial_payment_type', 'credit'])]
    #[TestWith(['initial_payment_amount', null])]
    #[TestWith(['initial_payment_amount', '0'])]
    #[TestWith(['initial_payment_amount', '-1.00'])]
    #[TestWith(['initial_payment_amount', '1.001'])]
    #[TestWith(['initial_payment_amount', '1e1'])]
    #[TestWith(['initial_payment_amount', '100000000.00'])]
    #[TestWith(['payment_method', null])]
    #[TestWith(['payment_method', 'credit'])]
    public function test_invalid_initial_payments_do_not_create_sales(string $field, mixed $value): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);
        $payload = [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 1]], 'initial_payment_type' => 'partial',
            'initial_payment_amount' => '1.00', 'payment_method' => 'cash',
        ];
        $payload[$field] = $value;

        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertSessionHasErrors([$field]);

        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('payments');
    }

    public function test_cancelled_sales_cannot_receive_new_payments_or_contribute_to_outstanding_credit(): void
    {
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'customer_id' => $customerId, 'sale_type' => 'walk_in',
            'total_amount' => '100.00', 'status' => 'cancelled', 'sold_at' => '2026-10-24 10:00:00',
        ]);

        $this->actingAs($user)->post(route('sales.payments.store', $saleId), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'amount' => '1.00', 'payment_method' => 'cash',
        ])->assertSessionHasErrors(['amount' => 'Payments can only be recorded for completed sales.']);

        $this->assertDatabaseEmpty('payments');
        $this->get(route('customers.show', $customerId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('outstandingBalance', 0)->has('sales.data', 0));
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sale.outstanding_balance', 0));
    }

    #[TestWith(['999'])]
    #[TestWith(['invalid'])]
    public function test_missing_or_invalid_sales_return_404(string $sale): void
    {
        $this->actingAs(User::factory()->create())->post(route('sales.payments.store', $sale), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'amount' => '1.00', 'payment_method' => 'cash',
        ])->assertNotFound();

        $this->assertDatabaseEmpty('payments');
    }

    public function test_failed_initial_payment_rolls_back_sale_stock_items_and_token_and_allows_retry(): void
    {
        Exceptions::fake([QueryException::class]);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 2]);
        $payload = [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 2]], 'initial_payment_type' => 'full', 'payment_method' => 'cash',
        ];
        DB::statement("CREATE TRIGGER fail_payment BEFORE INSERT ON payments BEGIN SELECT RAISE(ABORT, 'Simulated payment failure'); END");

        $this->actingAs(User::factory()->create())->post(route('sales.store'), $payload)->assertServerError();

        Exceptions::assertReported(QueryException::class);
        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');
        $this->assertDatabaseEmpty('payments');
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 2]);

        DB::statement('DROP TRIGGER fail_payment');
        $this->post(route('sales.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['amount' => '50.00']);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 0]);
    }

    public function test_customer_balances_are_derived_from_completed_sales_and_valid_payments_without_join_duplication(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $otherCustomerId = DB::table('customers')->insertGetId(['name' => 'Other']);
        DB::table('customers')->insert(['name' => 'Zero']);
        $saleIds = [];
        foreach (['100.00', '200.00', '300.00', '400.00'] as $index => $total) {
            $saleIds[] = DB::table('sales')->insertGetId([
                'sale_number' => 'SALE-'.$index, 'user_id' => $user->id, 'customer_id' => $customerId, 'sale_type' => 'walk_in',
                'total_amount' => $total, 'status' => $index === 3 ? 'cancelled' : 'completed', 'sold_at' => '2026-10-24 10:00:00',
            ]);
        }
        DB::table('sales')->insert([
            'sale_number' => 'OTHER', 'user_id' => $user->id, 'customer_id' => $otherCustomerId, 'sale_type' => 'walk_in',
            'total_amount' => '900.00', 'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        DB::table('payments')->insert([
            ['sale_id' => $saleIds[0], 'amount' => '40.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:00:00'],
            ['sale_id' => $saleIds[0], 'amount' => '60.00', 'payment_method' => 'gcash', 'paid_at' => '2026-10-24 11:00:00'],
            ['sale_id' => $saleIds[1], 'amount' => '20.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:00:00'],
            ['sale_id' => $saleIds[1], 'amount' => '60.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:00:00'],
            ['sale_id' => $saleIds[1], 'amount' => '-500.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:00:00'],
            ['sale_id' => $saleIds[1], 'amount' => '0.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:00:00'],
            ['sale_id' => $saleIds[3], 'amount' => '10.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:00:00'],
        ]);

        $this->actingAs($user)->get(route('customers.show', $customerId))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('outstandingBalance', 420)->has('sales.data', 3)->has('payments.data', 5)
            ->where('payments.data.0.sale_status', 'cancelled')->where('sales.data.1.outstanding_balance', 120));

        $this->get(route('customers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.outstanding_balance', 420)->where('customers.data.1.outstanding_balance', 900)->where('customers.data.2.outstanding_balance', 0));
        $this->get(route('sales.show', $saleIds[1]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sale.amount_paid', 80)->where('sale.outstanding_balance', 120)->has('payments', 2));
        $this->post(route('sales.payments.store', $saleIds[1]), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'amount' => '121.00', 'payment_method' => 'cash',
        ])->assertSessionHasErrors(['amount']);

        DB::table('payments')->where('sale_id', $saleIds[1])->where('amount', '60.00')->update(['amount' => '70.00']);
        $this->get(route('customers.show', $customerId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('outstandingBalance', 410));
    }

    public function test_customer_payment_history_is_paginated_and_excludes_other_customers(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $otherCustomer = DB::table('customers')->insertGetId(['name' => 'Other']);
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-1', 'user_id' => $user->id, 'customer_id' => $customerId, 'sale_type' => 'walk_in',
            'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        $otherSale = DB::table('sales')->insertGetId([
            'sale_number' => 'OTHER', 'user_id' => $user->id, 'customer_id' => $otherCustomer, 'sale_type' => 'walk_in',
            'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-24 10:00:00',
        ]);
        for ($number = 1; $number <= 12; $number++) {
            DB::table('payments')->insert(['sale_id' => $saleId, 'amount' => '1.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 11:00:00']);
        }
        DB::table('payments')->insert(['sale_id' => $otherSale, 'amount' => '100.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 12:00:00']);

        $response = $this->actingAs($user)->get(route('customers.show', $customerId));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->has('payments.data', 10)->where('payments.total', 12)
            ->where('payments.data.0.sale_id', $saleId)->where('outstandingBalance', 88));
        $this->assertStringContainsString('payments_page=2', $response->inertiaProps('payments.next_page_url'));
        $this->get($response->inertiaProps('payments.next_page_url'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('payments.data', 2)->where('payments.current_page', 2));
    }

    #[TestWith(['sales_page'])]
    #[TestWith(['payments_page'])]
    public function test_customer_history_rejects_invalid_page_numbers(string $parameter): void
    {
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);

        $this->actingAs(User::factory()->create())->get(route('customers.show', ['customer' => $customerId, $parameter => 0]))->assertSessionHasErrors([$parameter]);
    }

    public function test_free_sales_do_not_create_zero_value_payments(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Free', 'price' => '0.00']);

        $this->actingAs(User::factory()->create())->post(route('sales.store'), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'sale_type' => 'walk_in',
            'items' => [['product_id' => $productId, 'quantity' => 1]], 'initial_payment_type' => 'full', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sales', ['total_amount' => '0.00']);
        $this->assertDatabaseEmpty('payments');
    }

    public function test_future_dated_payments_do_not_reduce_current_credit_or_allow_additional_payments(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $customer = DB::table('customers')->insertGetId(['name' => 'Imported customer']);
        $sale = DB::table('sales')->insertGetId([
            'sale_number' => 'IMPORTED', 'customer_id' => $customer, 'user_id' => $user->id, 'sale_type' => 'walk_in',
            'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-23 09:00:00',
        ]);
        DB::table('payments')->insert([
            ['sale_id' => $sale, 'amount' => '25.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-24 09:00:00'],
            ['sale_id' => $sale, 'amount' => '75.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-25 09:00:00'],
        ]);

        $this->actingAs($user)->get(route('sales.show', $sale))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sale.amount_paid', 25)->where('sale.outstanding_balance', 75));
        $this->get(route('sales.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sales.data.0.outstanding_balance', 75));
        $this->get(route('customers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('customers.data.0.outstanding_balance', 75));
        $this->get(route('customers.show', $customer))->assertOk()->assertInertia(fn (Assert $page) => $page->where('outstandingBalance', 75));
        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('credit.outstanding', 75));
        $this->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('outstandingCredit', 75));
        $this->post(route('sales.payments.store', $sale), [
            'submission_token' => '11111111-1111-4111-8111-111111111111', 'amount' => '75.00', 'payment_method' => 'cash',
        ])->assertSessionHasErrors(['amount' => 'This sale has a future-dated payment. Correct that record before adding another payment.']);

        $this->assertDatabaseCount('payments', 2);
    }
}
