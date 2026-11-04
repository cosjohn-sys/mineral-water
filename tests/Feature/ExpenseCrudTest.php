<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ExpenseCrudTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', 'expenses.index', []])]
    #[TestWith(['GET', 'expenses.create', []])]
    #[TestWith(['POST', 'expenses.store', []])]
    #[TestWith(['GET', 'expenses.show', ['expense' => 1]])]
    #[TestWith(['GET', 'expenses.edit', ['expense' => 1]])]
    #[TestWith(['PUT', 'expenses.update', ['expense' => 1]])]
    #[TestWith(['DELETE', 'expenses.destroy', ['expense' => 1]])]
    /**
     * @param  array{expense?: int}  $parameters
     */
    public function test_guests_cannot_manage_expenses(string $method, string $route, array $parameters): void
    {
        $this->call($method, route($route, $parameters))->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('expenses');
    }

    public function test_expenses_are_saved_using_only_validated_fields_and_displayed(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));

        $response = $this->actingAs(User::factory()->create())->post(route('expenses.store'), [
            'description' => 'Delivery fuel', 'category' => 'Transport', 'amount' => '150.25', 'expense_date' => '2026-10-23',
            'id' => 999, 'created_at' => '2000-01-01 00:00:00',
        ]);

        $expenseId = DB::table('expenses')->value('id');
        $response->assertSessionHasNoErrors()->assertRedirectToRoute('expenses.show', $expenseId)->assertSessionHas('status', 'Expense added.');
        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'description' => 'Delivery fuel', 'category' => 'Transport', 'amount' => '150.25', 'expense_date' => '2026-10-23', 'created_at' => '2026-10-24 12:00:00']);
        $this->assertDatabaseMissing('expenses', ['id' => 999]);
        $this->get(route('expenses.show', $expenseId))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Expenses/Show')->where('expense.description', 'Delivery fuel'));
    }

    public function test_expense_forms_are_displayed_with_the_current_date(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $expenseId = DB::table('expenses')->insertGetId(['description' => 'Fuel', 'category' => 'Transport', 'amount' => '10.00', 'expense_date' => '2026-10-20']);

        $this->actingAs(User::factory()->create())->get(route('expenses.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Expenses/Form')->where('expense', null)->where('today', '2026-10-24'));
        $this->get(route('expenses.edit', $expenseId))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Expenses/Form')->where('expense.id', $expenseId));
    }

    public function test_expense_updates_preserve_creation_time_and_change_the_report_total(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $expenseId = DB::table('expenses')->insertGetId(['description' => 'Fuel', 'category' => 'Transport', 'amount' => '10.00', 'expense_date' => '2026-10-20', 'created_at' => '2026-10-20 09:00:00']);

        $this->actingAs(User::factory()->create())->put(route('expenses.update', $expenseId), [
            'description' => 'Power bill', 'category' => 'Utilities', 'amount' => '25.50', 'expense_date' => '2026-10-24', 'created_at' => '2000-01-01 00:00:00',
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('expenses.show', $expenseId);

        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'description' => 'Power bill', 'category' => 'Utilities', 'amount' => '25.50', 'expense_date' => '2026-10-24', 'created_at' => '2026-10-20 09:00:00', 'updated_at' => '2026-10-24 12:00:00']);
        $this->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.expenses', 25.5)->where('summary.sales_revenue', 0));
    }

    public function test_deleted_expenses_are_removed_from_reports_without_changing_payments(): void
    {
        $expenseId = DB::table('expenses')->insertGetId(['description' => 'Fuel', 'category' => 'Transport', 'amount' => '10.00', 'expense_date' => '2026-10-20']);

        $this->actingAs(User::factory()->create())->delete(route('expenses.destroy', $expenseId))->assertRedirectToRoute('expenses.index')->assertSessionHas('status', 'Expense deleted.');

        $this->assertDatabaseEmpty('expenses');
        $this->assertDatabaseEmpty('payments');
        $this->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.expenses', 0));
    }

    public function test_required_expense_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->create())->post(route('expenses.store'), [])->assertSessionHasErrors(['description', 'category', 'amount', 'expense_date']);

        $this->assertDatabaseEmpty('expenses');
    }

    #[TestWith(['description', 12])]
    #[TestWith(['category', ['invalid']])]
    #[TestWith(['amount', 0])]
    #[TestWith(['amount', '-1.00'])]
    #[TestWith(['amount', '1.001'])]
    #[TestWith(['amount', '1e2'])]
    #[TestWith(['amount', '100000000.00'])]
    #[TestWith(['expense_date', '2026-02-30'])]
    #[TestWith(['expense_date', '2026-10-25'])]
    public function test_invalid_expenses_leave_existing_values_unchanged(string $field, mixed $value): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 12:00:00'));
        $expenseId = DB::table('expenses')->insertGetId(['description' => 'Fuel', 'category' => 'Transport', 'amount' => '10.00', 'expense_date' => '2026-10-20']);
        $payload = ['description' => 'Changed', 'category' => 'Changed', 'amount' => '25.00', 'expense_date' => '2026-10-24'];
        $payload[$field] = $value;

        $this->actingAs(User::factory()->create())->put(route('expenses.update', $expenseId), $payload)->assertSessionHasErrors([$field]);

        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'description' => 'Fuel', 'category' => 'Transport', 'amount' => '10.00']);
    }

    public function test_expenses_reject_text_exceeding_storage_limits(): void
    {
        $this->actingAs(User::factory()->create())->post(route('expenses.store'), [
            'description' => str_repeat('a', 10001), 'category' => str_repeat('b', 256), 'amount' => '1.00', 'expense_date' => '2026-10-20',
        ])->assertSessionHasErrors(['description', 'category']);

        $this->assertDatabaseEmpty('expenses');
    }

    public function test_date_and_category_filters_paginate_without_losing_the_full_total(): void
    {
        for ($number = 1; $number <= 12; $number++) {
            DB::table('expenses')->insert(['description' => 'Fuel '.$number, 'category' => 'Transport', 'amount' => '2.10', 'expense_date' => '2026-10-24']);
        }
        DB::table('expenses')->insert([
            ['description' => 'Old', 'category' => 'Transport', 'amount' => '1000.00', 'expense_date' => '2026-10-23'],
            ['description' => 'Other', 'category' => 'Utilities', 'amount' => '100.00', 'expense_date' => '2026-10-24'],
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('expenses.index', ['date_from' => '2026-10-24', 'date_to' => '2026-10-24', 'category' => 'Transport', 'per_page' => 10]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Expenses/Index')->has('expenses.data', 10)->where('expenses.total', 12)->where('totalExpenses', 25.2)->where('expenses.data.0.description', 'Fuel 12'));
        $this->assertStringContainsString('category=Transport', $response->inertiaProps('expenses.next_page_url'));
        $this->assertStringContainsString('date_from=2026-10-24', $response->inertiaProps('expenses.next_page_url'));
        $this->get($response->inertiaProps('expenses.next_page_url'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('expenses.data', 2)->where('totalExpenses', 25.2));
    }

    #[TestWith(['date_from', 'invalid'])]
    #[TestWith(['date_to', '2026-10-01'])]
    #[TestWith(['category', ['invalid']])]
    #[TestWith(['per_page', 999])]
    #[TestWith(['page', 0])]
    public function test_invalid_expense_filters_are_rejected(string $field, mixed $value): void
    {
        $filters = ['date_from' => '2026-10-24'];
        $filters[$field] = $value;

        $this->actingAs(User::factory()->create())->get(route('expenses.index', $filters))->assertSessionHasErrors([$field]);
    }

    #[TestWith(['GET', 'expenses.show'])]
    #[TestWith(['GET', 'expenses.edit'])]
    #[TestWith(['PUT', 'expenses.update'])]
    #[TestWith(['DELETE', 'expenses.destroy'])]
    public function test_missing_expenses_return_404(string $method, string $route): void
    {
        $this->actingAs(User::factory()->create())->call($method, route($route, 999), ['description' => 'Fuel', 'category' => 'Transport', 'amount' => '1.00', 'expense_date' => '2026-10-20'])->assertNotFound();
    }

    public function test_expense_text_is_escaped_and_category_filters_bind_values(): void
    {
        $text = '<script>alert("expense")</script>';
        $expenseId = DB::table('expenses')->insertGetId(['description' => $text, 'category' => $text, 'amount' => '1.00', 'expense_date' => '2026-10-20']);

        $this->actingAs(User::factory()->create())->get(route('expenses.show', $expenseId))->assertOk()->assertDontSee($text, false)->assertInertia(fn (Assert $page) => $page->where('expense.description', $text)->where('expense.category', $text));
        $this->get(route('expenses.index', ['category' => "' OR 1=1 --"]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('expenses.data', 0));

    }
}
