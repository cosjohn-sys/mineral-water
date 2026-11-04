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

class PaymentReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_record_reversals(): void
    {
        $this->post(route('payments.reversal.store', 1), ['reason' => 'Returned'])->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('payment_reversals');
    }

    #[TestWith(['cash'])]
    #[TestWith(['gcash'])]
    public function test_full_reversals_preserve_original_payments_and_reopen_credit_without_changing_gallons_or_stock(string $method): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ana']);
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '100.00', 'stock_quantity' => 9]);
        $saleId = DB::table('sales')->insertGetId(['sale_number' => 'SALE-1', 'user_id' => $user->id, 'customer_id' => $customerId, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-20 10:00:00']);
        $paymentId = DB::table('payments')->insertGetId(['sale_id' => $saleId, 'amount' => '100.00', 'payment_method' => $method, 'paid_at' => '2026-10-20 10:00:00']);
        DB::table('gallon_exchanges')->insert(['sale_id' => $saleId, 'filled_gallons_out' => 3, 'empty_gallons_returned' => 2]);

        $this->actingAs($user)->post(route('payments.reversal.store', $paymentId), ['reason' => 'Money returned', 'amount' => '0.01', 'payment_id' => 999, 'user_id' => 999, 'reversed_at' => '2000-01-01'])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('sales.show', $saleId)->assertSessionHas('status', 'Full payment reversal recorded.');

        $this->assertDatabaseHas('payment_reversals', ['payment_id' => $paymentId, 'user_id' => $user->id, 'reason' => 'Money returned', 'reversed_at' => '2026-10-24 12:00:00']);
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'amount' => '100.00', 'payment_method' => $method]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total_amount' => '100.00', 'status' => 'completed']);
        $this->assertDatabaseHas('gallon_exchanges', ['sale_id' => $saleId, 'filled_gallons_out' => 3, 'empty_gallons_returned' => 2]);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 9]);
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sale.amount_paid', 0)->where('sale.outstanding_balance', 100)
            ->where('sale.cash_received', 0)->where('sale.gcash_received', 0)->has('payments', 1)->where('payments.0.reversed_at', '2026-10-24 12:00:00'));
        $this->get(route('customers.show', $customerId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('outstandingBalance', 100)->where('payments.data.0.reversal_reason', 'Money returned'));
        $this->get(route('customers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('customers.data.0.outstanding_balance', 100));
        $this->get(route('sales.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sales.data.0.outstanding_balance', 100));
        $this->get(route('reports.index', ['date_from' => '2026-10-24', 'date_to' => '2026-10-24']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.sales_revenue', 0)->where('summary.reversals', 100)->where('summary.net_collections', -100)->where('outstandingCredit', 100));
    }

    public function test_retrying_a_reversal_is_idempotent_and_a_replacement_payment_can_settle_the_sale(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId(['sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-20 10:00:00']);
        $paymentId = DB::table('payments')->insertGetId(['sale_id' => $saleId, 'amount' => '100.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-20 10:00:00']);
        $this->actingAs($user)->post(route('payments.reversal.store', $paymentId), ['reason' => 'Incorrect collection'])->assertSessionHasNoErrors();

        $this->post(route('payments.reversal.store', $paymentId), ['reason' => 'Retry'])->assertSessionHasNoErrors()->assertSessionHas('status', 'Payment already reversed.');

        $this->assertDatabaseCount('payment_reversals', 1);
        $this->assertDatabaseHas('payment_reversals', ['reason' => 'Incorrect collection']);
        $this->post(route('sales.payments.store', $saleId), ['submission_token' => '11111111-1111-4111-8111-111111111111', 'amount' => '100.00', 'payment_method' => 'gcash'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 2);
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sale.amount_paid', 100)->where('sale.outstanding_balance', 0)->where('sale.cash_received', 0)->where('sale.gcash_received', 100));
    }

    public function test_cancelled_sale_collections_are_only_deducted_after_an_explicit_reversal(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId(['sale_number' => 'CANCELLED', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'cancelled', 'sold_at' => '2026-10-20 10:00:00']);
        $paymentId = DB::table('payments')->insertGetId(['sale_id' => $saleId, 'amount' => '100.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-20 10:00:00']);
        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.sales_revenue', 0)->where('summary.net_collections', 100)->where('outstandingCredit', 0));

        $this->post(route('payments.reversal.store', $paymentId), ['reason' => 'Cancelled sale refund'])->assertSessionHasNoErrors();

        $this->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.sales_revenue', 0)->where('summary.gross_collections', 100)->where('summary.reversals', 100)->where('summary.net_collections', 0)->where('outstandingCredit', 0));
        $this->assertDatabaseCount('payments', 1);
    }

    #[TestWith([null])]
    #[TestWith(['   '])]
    #[TestWith([123])]
    public function test_reversal_reasons_are_required_and_must_be_text(mixed $reason): void
    {
        $this->actingAs(User::factory()->create())->post(route('payments.reversal.store', 1), ['reason' => $reason])->assertSessionHasErrors(['reason']);

        $this->assertDatabaseEmpty('payment_reversals');
    }

    public function test_reversal_reasons_cannot_exceed_the_storage_limit(): void
    {
        $this->actingAs(User::factory()->create())->post(route('payments.reversal.store', 1), ['reason' => str_repeat('a', 1001)])->assertSessionHasErrors(['reason']);

        $this->assertDatabaseEmpty('payment_reversals');
    }

    #[TestWith(['0.00', '2026-10-20 10:00:00'])]
    #[TestWith(['-1.00', '2026-10-20 10:00:00'])]
    #[TestWith(['1.00', '2026-10-25 10:00:00'])]
    public function test_invalid_or_future_payments_cannot_be_reversed(string $amount, string $paidAt): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId(['sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-20 10:00:00']);
        $paymentId = DB::table('payments')->insertGetId(['sale_id' => $saleId, 'amount' => $amount, 'payment_method' => 'cash', 'paid_at' => $paidAt]);

        $this->actingAs($user)->post(route('payments.reversal.store', $paymentId), ['reason' => 'Invalid'])->assertSessionHasErrors(['reason' => 'Only a valid payment already received can be reversed.']);

        $this->assertDatabaseEmpty('payment_reversals');
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'amount' => $amount]);
    }

    #[TestWith(['999'])]
    #[TestWith(['invalid'])]
    public function test_missing_or_invalid_payment_identifiers_return_404(string $payment): void
    {
        $this->actingAs(User::factory()->create())->post(route('payments.reversal.store', $payment), ['reason' => 'Returned'])->assertNotFound();

        $this->assertDatabaseEmpty('payment_reversals');
    }

    public function test_failed_reversal_insertion_keeps_the_payment_valid_and_can_be_retried(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        Exceptions::fake([QueryException::class]);
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId(['sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-20 10:00:00']);
        $paymentId = DB::table('payments')->insertGetId(['sale_id' => $saleId, 'amount' => '100.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-20 10:00:00']);
        DB::statement("CREATE TRIGGER fail_reversal BEFORE INSERT ON payment_reversals BEGIN SELECT RAISE(ABORT, 'Simulated reversal failure'); END");

        $this->actingAs($user)->post(route('payments.reversal.store', $paymentId), ['reason' => 'Returned'])->assertServerError();

        Exceptions::assertReported(QueryException::class);
        $this->assertDatabaseEmpty('payment_reversals');
        $this->get(route('sales.show', $saleId))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sale.outstanding_balance', 0));
        DB::statement('DROP TRIGGER fail_reversal');
        $this->post(route('payments.reversal.store', $paymentId), ['reason' => 'Returned'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_reversals', 1);
    }

    public function test_reversal_reasons_are_rendered_as_escaped_data(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $user = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId(['sale_number' => 'SALE-1', 'user_id' => $user->id, 'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => '2026-10-20 10:00:00']);
        $paymentId = DB::table('payments')->insertGetId(['sale_id' => $saleId, 'amount' => '100.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-20 10:00:00']);
        $reason = '<script>alert("reversal")</script>';
        $this->actingAs($user)->post(route('payments.reversal.store', $paymentId), ['reason' => $reason])->assertSessionHasNoErrors();

        $this->get(route('sales.show', $saleId))->assertOk()->assertDontSee($reason, false)->assertInertia(fn (Assert $page) => $page->where('payments.0.reversal_reason', $reason));
    }
}
