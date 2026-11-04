<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', 'customers.index', []])]
    #[TestWith(['GET', 'customers.create', []])]
    #[TestWith(['POST', 'customers.store', []])]
    #[TestWith(['GET', 'customers.show', ['customer' => 1]])]
    #[TestWith(['GET', 'customers.edit', ['customer' => 1]])]
    #[TestWith(['PUT', 'customers.update', ['customer' => 1]])]
    #[TestWith(['DELETE', 'customers.destroy', ['customer' => 1]])]
    /**
     * @param  array{customer?: int}  $parameters
     */
    public function test_guests_cannot_manage_customers(string $method, string $route, array $parameters): void
    {
        $this->call($method, route($route, $parameters), ['name' => 'Customer'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('customers');
    }

    public function test_customers_are_paginated_ten_at_a_time(): void
    {
        $user = User::factory()->create();
        for ($number = 1; $number <= 12; $number++) {
            DB::table('customers')->insert(['name' => sprintf('Customer %02d', $number)]);
        }

        $this->actingAs($user)->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Index')
                ->has('customers.data', 10)
                ->where('customers.total', 12)
                ->where('customers.per_page', 10)
                ->where('customers.data.0.name', 'Customer 01')
                ->where('filters.per_page', 10));

        $this->get(route('customers.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 2)
                ->where('customers.current_page', 2)
                ->where('customers.data.0.name', 'Customer 11'));
    }

    #[TestWith(['Ada'])]
    #[TestWith(['09171234567'])]
    #[TestWith(['Riverside'])]
    public function test_search_matches_customer_names_phones_and_addresses(string $search): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Ada Reyes', 'phone' => '09171234567', 'address' => 'Riverside',
        ]);
        DB::table('customers')->insert(['name' => 'Grace Cruz', 'phone' => '09189999999', 'address' => 'Hill Road']);

        $this->actingAs(User::factory()->create())->get(route('customers.index', ['search' => $search]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.id', $customerId)
                ->where('filters.search', $search));
    }

    public function test_customer_pagination_preserves_search_and_page_size(): void
    {
        for ($number = 1; $number <= 26; $number++) {
            DB::table('customers')->insert(['name' => sprintf('River %02d', $number)]);
        }
        DB::table('customers')->insert(['name' => 'Hill customer']);

        $response = $this->actingAs(User::factory()->create())->get(route('customers.index', [
            'search' => 'River', 'per_page' => 25, 'page' => 2,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.total', 26)
            ->where('customers.per_page', 25)
            ->where('customers.data.0.name', 'River 26'));
        $this->assertStringContainsString('search=River', $response->inertiaProps('customers.prev_page_url'));
        $this->assertStringContainsString('per_page=25', $response->inertiaProps('customers.prev_page_url'));
    }

    #[TestWith(['per_page', 500, 'The selected per page is invalid.'])]
    #[TestWith(['page', 0, 'The page field must be at least 1.'])]
    #[TestWith(['search', ['invalid'], 'The search field must be a string.'])]
    public function test_customer_lists_reject_invalid_filters(string $field, mixed $value, string $message): void
    {
        $this->actingAs(User::factory()->create())->get(route('customers.index', [$field => $value]))
            ->assertSessionHasErrors([$field => $message]);
    }

    public function test_customer_search_binds_input_as_a_value(): void
    {
        DB::table('customers')->insert(['name' => 'Ada Reyes']);

        $this->actingAs(User::factory()->create())->get(route('customers.index', ['search' => "' OR 1=1 --"]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_creation_form_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())->get(route('customers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Form')
                ->where('customer', null));
    }

    #[TestWith(['customers.show', 'Customers/Show'])]
    #[TestWith(['customers.edit', 'Customers/Form'])]
    public function test_customer_details_and_edit_form_are_displayed(string $route, string $component): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Ada Reyes', 'phone' => '09171234567', 'address' => 'Riverside',
        ]);

        $this->actingAs(User::factory()->create())->get(route($route, $customerId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('customer.id', $customerId)
                ->where('customer.name', 'Ada Reyes')
                ->where('customer.phone', '09171234567')
                ->where('customer.address', 'Riverside'));
    }

    public function test_customers_can_be_added_using_only_validated_fields(): void
    {
        $this->freezeTime();

        $response = $this->actingAs(User::factory()->create())->post(route('customers.store'), [
            'name' => "O'Reilly", 'phone' => '09171234567', 'address' => 'Riverside',
            'id' => 999, 'created_at' => '2000-01-01 00:00:00',
        ]);

        $customerId = DB::table('customers')->where('name', "O'Reilly")->value('id');
        $response->assertSessionHasNoErrors()->assertRedirectToRoute('customers.show', $customerId)
            ->assertSessionHas('status', 'Customer added.');
        $this->assertDatabaseHas('customers', [
            'name' => "O'Reilly", 'phone' => '09171234567', 'address' => 'Riverside',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseMissing('customers', ['id' => 999]);
    }

    public function test_customers_can_be_added_without_phone_or_address(): void
    {
        $this->actingAs(User::factory()->create())->post(route('customers.store'), ['name' => 'Ada Reyes'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('customers', ['name' => 'Ada Reyes', 'phone' => null, 'address' => null]);
    }

    public function test_customers_can_be_edited_and_optional_fields_cleared(): void
    {
        $this->freezeTime();
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Ada Reyes', 'phone' => '09171234567', 'address' => 'Riverside',
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);

        $this->actingAs(User::factory()->create())->put(route('customers.update', $customerId), [
            'name' => 'Ada Cruz', 'phone' => '', 'address' => '',
            'id' => 999, 'created_at' => '2000-01-01 00:00:00',
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('customers.show', $customerId)
            ->assertSessionHas('status', 'Customer updated.');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId, 'name' => 'Ada Cruz', 'phone' => null, 'address' => null,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_customer_names_are_required(): void
    {
        $this->actingAs(User::factory()->create())->post(route('customers.store'), [])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);

        $this->assertDatabaseEmpty('customers');
    }

    public function test_customer_fields_must_be_strings(): void
    {
        $this->actingAs(User::factory()->create())->post(route('customers.store'), [
            'name' => 123, 'phone' => ['123'], 'address' => ['Riverside'],
        ])->assertSessionHasErrors([
            'name' => 'The name field must be a string.',
            'phone' => 'The phone field must be a string.',
            'address' => 'The address field must be a string.',
        ]);

        $this->assertDatabaseEmpty('customers');
    }

    public function test_customer_fields_reject_values_exceeding_their_limits(): void
    {
        $this->actingAs(User::factory()->create())->post(route('customers.store'), [
            'name' => str_repeat('a', 256), 'phone' => str_repeat('1', 256), 'address' => str_repeat('a', 10001),
        ])->assertSessionHasErrors([
            'name' => 'The name field must not be greater than 255 characters.',
            'phone' => 'The phone field must not be greater than 255 characters.',
            'address' => 'The address field must not be greater than 10000 characters.',
        ]);

        $this->assertDatabaseEmpty('customers');
    }

    public function test_invalid_customer_edits_leave_the_record_unchanged(): void
    {
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ada Reyes']);

        $this->actingAs(User::factory()->create())->put(route('customers.update', $customerId), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);

        $this->assertDatabaseHas('customers', ['id' => $customerId, 'name' => 'Ada Reyes']);
    }

    public function test_customers_without_sales_can_be_deleted(): void
    {
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ada Reyes']);

        $this->actingAs(User::factory()->create())->delete(route('customers.destroy', $customerId))
            ->assertRedirectToRoute('customers.index')->assertSessionHas('status', 'Customer deleted.');

        $this->assertDatabaseMissing('customers', ['id' => $customerId]);
    }

    public function test_customers_with_sales_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $customerId = DB::table('customers')->insertGetId(['name' => 'Ada Reyes']);
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-0001', 'customer_id' => $customerId, 'user_id' => $user->id,
            'sale_type' => 'delivery', 'total_amount' => '50.00', 'status' => 'completed',
            'sold_at' => '2026-10-23 09:00:00',
        ]);

        $this->actingAs($user)->get(route('customers.show', $customerId))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('hasSales', true));
        $this->from(route('customers.show', $customerId))->delete(route('customers.destroy', $customerId))
            ->assertRedirectToRoute('customers.show', $customerId)
            ->assertSessionHasErrors(['customer' => 'Customers with sales cannot be deleted. Their financial history must be preserved.']);

        $this->assertDatabaseHas('customers', ['id' => $customerId]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'customer_id' => $customerId, 'total_amount' => '50.00']);
    }

    #[TestWith(['GET', 'customers.show'])]
    #[TestWith(['GET', 'customers.edit'])]
    #[TestWith(['PUT', 'customers.update'])]
    #[TestWith(['DELETE', 'customers.destroy'])]
    public function test_missing_customers_return_404(string $method, string $route): void
    {
        $this->actingAs(User::factory()->create())->call($method, route($route, 999), ['name' => 'Ada Reyes'])
            ->assertNotFound();

        $this->assertDatabaseEmpty('customers');
    }

    public function test_non_numeric_customer_identifiers_return_404(): void
    {
        $this->actingAs(User::factory()->create())->get(route('customers.show', 'invalid'))->assertNotFound();
    }

    public function test_customer_contact_fields_are_kept_as_data_in_the_rendered_page(): void
    {
        $name = '<script>alert("name")</script>';
        $phone = '<script>alert("phone")</script>';
        $address = '<script>alert("address")</script>';
        $customerId = DB::table('customers')->insertGetId(['name' => $name, 'phone' => $phone, 'address' => $address]);

        $this->actingAs(User::factory()->create())->get(route('customers.show', $customerId))
            ->assertOk()->assertDontSee($name, false)->assertDontSee($phone, false)->assertDontSee($address, false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.name', $name)->where('customer.phone', $phone)->where('customer.address', $address));
    }
}
