<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', 'products.index', []])]
    #[TestWith(['GET', 'products.create', []])]
    #[TestWith(['POST', 'products.store', []])]
    #[TestWith(['GET', 'products.show', ['product' => 1]])]
    #[TestWith(['GET', 'products.edit', ['product' => 1]])]
    #[TestWith(['PUT', 'products.update', ['product' => 1]])]
    #[TestWith(['DELETE', 'products.destroy', ['product' => 1]])]
    /**
     * @param  array{product?: int}  $parameters
     */
    public function test_guests_cannot_manage_products(string $method, string $route, array $parameters): void
    {
        $this->call($method, route($route, $parameters), ['name' => 'Refill', 'price' => '25.00'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('products');
    }

    public function test_products_are_paginated_ten_at_a_time(): void
    {
        for ($number = 1; $number <= 12; $number++) {
            DB::table('products')->insert(['name' => sprintf('Product %02d', $number), 'price' => '25.00']);
        }

        $this->actingAs(User::factory()->create())->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Products/Index')
                ->has('products.data', 10)
                ->where('products.total', 12)
                ->where('products.per_page', 10)
                ->where('products.data.0.name', 'Product 01')
                ->where('filters.per_page', 10));

        $this->get(route('products.index', ['page' => 2]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('products.current_page', 2)
            ->where('products.data.0.name', 'Product 11'));
    }

    #[TestWith(['all', 2])]
    #[TestWith(['active', 1])]
    #[TestWith(['inactive', 1])]
    public function test_product_search_and_status_filters_work_together(string $status, int $count): void
    {
        DB::table('products')->insert([
            ['name' => 'Refill A', 'price' => '25.00', 'is_active' => true],
            ['name' => 'Refill B', 'price' => '30.00', 'is_active' => false],
            ['name' => 'Bottle', 'price' => '100.00', 'is_active' => true],
        ]);

        $this->actingAs(User::factory()->create())->get(route('products.index', ['search' => 'Refill', 'status' => $status]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', $count)
            ->where('products.total', $count)
            ->where('filters.search', 'Refill')
            ->where('filters.status', $status));
    }

    public function test_product_pagination_preserves_search_status_and_page_size(): void
    {
        for ($number = 1; $number <= 12; $number++) {
            DB::table('products')->insert(['name' => sprintf('Refill %02d', $number), 'price' => '25.00', 'is_active' => false]);
        }
        DB::table('products')->insert(['name' => 'Refill active', 'price' => '25.00']);

        $response = $this->actingAs(User::factory()->create())->get(route('products.index', [
            'search' => 'Refill', 'status' => 'inactive', 'per_page' => 10,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 10)->where('products.total', 12));
        $this->assertStringContainsString('search=Refill', $response->inertiaProps('products.next_page_url'));
        $this->assertStringContainsString('status=inactive', $response->inertiaProps('products.next_page_url'));
        $this->assertStringContainsString('per_page=10', $response->inertiaProps('products.next_page_url'));

        $this->get(route('products.index', ['search' => 'Refill', 'status' => 'inactive', 'per_page' => 25]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 12)->where('products.per_page', 25));
    }

    #[TestWith(['per_page', 500, 'The selected per page is invalid.'])]
    #[TestWith(['status', 'unknown', 'The selected status is invalid.'])]
    #[TestWith(['page', 0, 'The page field must be at least 1.'])]
    #[TestWith(['search', ['invalid'], 'The search field must be a string.'])]
    public function test_product_lists_reject_invalid_filters(string $field, mixed $value, string $message): void
    {
        $this->actingAs(User::factory()->create())->get(route('products.index', [$field => $value]))
            ->assertSessionHasErrors([$field => $message]);
    }

    public function test_product_search_binds_input_as_a_value(): void
    {
        DB::table('products')->insert(['name' => 'Refill', 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->get(route('products.index', ['search' => "' OR 1=1 --"]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 0));

        $this->assertDatabaseCount('products', 1);
    }

    public function test_product_creation_form_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())->get(route('products.create'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Products/Form')->where('product', null));
    }

    #[TestWith(['products.show', 'Products/Show'])]
    #[TestWith(['products.edit', 'Products/Form'])]
    public function test_product_details_and_edit_form_are_displayed(string $route, string $component): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->get(route($route, $productId))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component($component)->where('product.id', $productId)
            ->where('product.name', 'Refill')->where('product.stock_quantity', null));
    }

    public function test_products_default_to_active_without_stock_tracking(): void
    {
        $this->freezeTime();

        $response = $this->actingAs(User::factory()->create())->post(route('products.store'), ['name' => 'Refill', 'price' => '25.00']);

        $productId = DB::table('products')->where('name', 'Refill')->value('id');
        $response->assertSessionHasNoErrors()->assertRedirectToRoute('products.show', $productId)
            ->assertSessionHas('status', 'Product added.');
        $this->assertDatabaseHas('products', [
            'id' => $productId, 'name' => 'Refill', 'price' => '25.00', 'stock_quantity' => null, 'is_active' => true,
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_products_can_be_added_using_only_validated_fields(): void
    {
        $this->freezeTime();

        $response = $this->actingAs(User::factory()->create())->post(route('products.store'), [
            'name' => 'Bottle', 'price' => '35.50', 'stock_quantity' => 12, 'is_active' => false,
            'id' => 999, 'created_at' => '2000-01-01 00:00:00',
        ]);

        $productId = DB::table('products')->where('name', 'Bottle')->value('id');
        $response->assertSessionHasNoErrors()->assertRedirectToRoute('products.show', $productId);
        $this->assertDatabaseHas('products', [
            'name' => 'Bottle', 'price' => '35.50', 'stock_quantity' => 12, 'is_active' => false,
            'created_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseMissing('products', ['id' => 999]);
    }

    #[TestWith(['0', 0])]
    #[TestWith(['99999999.99', 4294967295])]
    public function test_product_price_and_stock_accept_their_boundary_values(string $price, int $stockQuantity): void
    {
        $this->actingAs(User::factory()->create())->post(route('products.store'), [
            'name' => 'Refill', 'price' => $price, 'stock_quantity' => $stockQuantity,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Refill', 'price' => $price, 'stock_quantity' => $stockQuantity]);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_product_edits_can_change_details_clear_stock_and_change_active_status(bool $isActive): void
    {
        $this->freezeTime();
        $productId = DB::table('products')->insertGetId([
            'name' => 'Bottle', 'price' => '100.00', 'stock_quantity' => 10, 'is_active' => ! $isActive,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);

        $this->actingAs(User::factory()->create())->put(route('products.update', $productId), [
            'name' => 'Refill', 'price' => '30.00', 'stock_quantity' => '', 'is_active' => $isActive,
            'id' => 999, 'created_at' => '2000-01-01 00:00:00',
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('products.show', $productId)
            ->assertSessionHas('status', 'Product updated.');

        $this->assertDatabaseHas('products', [
            'id' => $productId, 'name' => 'Refill', 'price' => '30.00', 'stock_quantity' => null, 'is_active' => $isActive,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_product_name_and_price_are_required(): void
    {
        $this->actingAs(User::factory()->create())->post(route('products.store'), [])
            ->assertSessionHasErrors(['name' => 'The name field is required.', 'price' => 'The price field is required.']);

        $this->assertDatabaseEmpty('products');
    }

    #[TestWith(['name', 123, 'The name field must be a string.'])]
    #[TestWith(['price', 'invalid', 'The price field must be a number.'])]
    #[TestWith(['price', -1, 'The price field must be at least 0.'])]
    #[TestWith(['price', '100000000.00', 'The price field must not be greater than 99999999.99.'])]
    #[TestWith(['price', '25.123', 'The price field must have 0-2 decimal places.'])]
    #[TestWith(['stock_quantity', -1, 'The stock quantity field must be at least 0.'])]
    #[TestWith(['stock_quantity', 1.5, 'The stock quantity field must be an integer.'])]
    #[TestWith(['stock_quantity', 4294967296, 'The stock quantity field must not be greater than 4294967295.'])]
    #[TestWith(['is_active', 'yes', 'The is active field must be true or false.'])]
    public function test_products_reject_invalid_field_values(string $field, mixed $value, string $message): void
    {
        $payload = ['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => null, 'is_active' => true];
        $payload[$field] = $value;

        $this->actingAs(User::factory()->create())->post(route('products.store'), $payload)
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseEmpty('products');
    }

    public function test_product_names_reject_values_exceeding_the_column_limit(): void
    {
        $this->actingAs(User::factory()->create())->post(route('products.store'), [
            'name' => str_repeat('a', 256), 'price' => '25.00',
        ])->assertSessionHasErrors(['name' => 'The name field must not be greater than 255 characters.']);

        $this->assertDatabaseEmpty('products');
    }

    public function test_invalid_product_edits_leave_the_record_unchanged(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->put(route('products.update', $productId), [
            'name' => 'Changed', 'price' => '-1', 'is_active' => false,
        ])->assertSessionHasErrors(['price' => 'The price field must be at least 0.']);

        $this->assertDatabaseHas('products', ['id' => $productId, 'name' => 'Refill', 'price' => '25.00', 'is_active' => true]);
    }

    public function test_product_deactivation_preserves_the_product_and_financial_records(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10]);
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-0001', 'user_id' => $user->id, 'sale_type' => 'walk_in',
            'total_amount' => '50.00', 'status' => 'completed', 'sold_at' => '2026-10-23 09:00:00',
        ]);
        $itemId = DB::table('sale_items')->insertGetId([
            'sale_id' => $saleId, 'product_id' => $productId, 'quantity' => 2, 'unit_price' => '25.00', 'subtotal' => '50.00',
        ]);

        $this->actingAs($user)->delete(route('products.destroy', $productId))
            ->assertRedirectToRoute('products.show', $productId)->assertSessionHas('status', 'Product deactivated.');

        $this->assertDatabaseHas('products', [
            'id' => $productId, 'name' => 'Refill', 'price' => '25.00', 'stock_quantity' => 10,
            'is_active' => false, 'updated_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total_amount' => '50.00']);
        $this->assertDatabaseHas('sale_items', ['id' => $itemId, 'product_id' => $productId, 'unit_price' => '25.00', 'subtotal' => '50.00']);
    }

    public function test_deactivating_an_already_inactive_product_succeeds(): void
    {
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00', 'is_active' => false]);

        $this->actingAs(User::factory()->create())->delete(route('products.destroy', $productId))
            ->assertRedirectToRoute('products.show', $productId);

        $this->assertDatabaseHas('products', ['id' => $productId, 'is_active' => false]);
    }

    public function test_product_price_edits_preserve_the_price_used_on_existing_sales(): void
    {
        $user = User::factory()->create();
        $productId = DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-0001', 'user_id' => $user->id, 'sale_type' => 'walk_in',
            'total_amount' => '50.00', 'status' => 'completed', 'sold_at' => '2026-10-23 09:00:00',
        ]);
        $itemId = DB::table('sale_items')->insertGetId([
            'sale_id' => $saleId, 'product_id' => $productId, 'quantity' => 2, 'unit_price' => '25.00', 'subtotal' => '50.00',
        ]);

        $this->actingAs($user)->put(route('products.update', $productId), ['name' => 'Refill', 'price' => '30.00'])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('products.show', $productId);

        $this->assertDatabaseHas('products', ['id' => $productId, 'price' => '30.00']);
        $this->assertDatabaseHas('sale_items', ['id' => $itemId, 'unit_price' => '25.00', 'subtotal' => '50.00']);
    }

    #[TestWith(['GET', 'products.show'])]
    #[TestWith(['GET', 'products.edit'])]
    #[TestWith(['PUT', 'products.update'])]
    #[TestWith(['DELETE', 'products.destroy'])]
    public function test_missing_products_return_404(string $method, string $route): void
    {
        $this->actingAs(User::factory()->create())->call($method, route($route, 999), ['name' => 'Refill', 'price' => '25.00'])
            ->assertNotFound();

        $this->assertDatabaseEmpty('products');
    }

    public function test_non_numeric_product_identifiers_return_404(): void
    {
        $this->actingAs(User::factory()->create())->get(route('products.show', 'invalid'))->assertNotFound();
    }

    public function test_product_names_are_kept_as_data_in_the_rendered_page(): void
    {
        $name = '<script>alert("product")</script>';
        $productId = DB::table('products')->insertGetId(['name' => $name, 'price' => '25.00']);

        $this->actingAs(User::factory()->create())->get(route('products.show', $productId))
            ->assertOk()->assertDontSee($name, false)
            ->assertInertia(fn (Assert $page) => $page->where('product.name', $name));
    }
}
