<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WaterSalesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_contact_details_and_product_stock_are_optional(): void
    {
        DB::table('customers')->insert(['name' => 'Walk-in customer']);
        DB::table('products')->insert(['name' => 'Refill', 'price' => '25.00']);

        $this->assertDatabaseHas('customers', [
            'name' => 'Walk-in customer',
            'phone' => null,
            'address' => null,
        ]);
        $this->assertDatabaseHas('products', [
            'name' => 'Refill',
            'stock_quantity' => null,
            'is_active' => true,
        ]);
    }

    public function test_walk_in_sales_do_not_require_a_customer_or_gallon_exchange(): void
    {
        $saleId = $this->createSale();

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'customer_id' => null,
            'delivery_address' => null,
            'sale_type' => 'walk_in',
        ]);
        $this->assertDatabaseEmpty('gallon_exchanges');
    }

    public function test_sale_numbers_must_be_unique(): void
    {
        $saleId = $this->createSale();
        $sale = (array) DB::table('sales')->find($saleId);
        unset($sale['id']);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('sales')->insert($sale);
    }

    public function test_a_sale_can_receive_multiple_cash_and_gcash_payments(): void
    {
        $saleId = $this->createSale();

        DB::table('payments')->insert([
            ['sale_id' => $saleId, 'amount' => '20.00', 'payment_method' => 'cash', 'paid_at' => '2026-10-23 09:00:00'],
            ['sale_id' => $saleId, 'amount' => '30.00', 'payment_method' => 'gcash', 'paid_at' => '2026-10-23 09:01:00'],
        ]);

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => '20.00', 'payment_method' => 'cash']);
        $this->assertDatabaseHas('payments', ['sale_id' => $saleId, 'amount' => '30.00', 'payment_method' => 'gcash']);
    }

    public function test_a_sale_allows_only_one_gallon_exchange_summary_with_zero_default_counts(): void
    {
        $saleId = $this->createSale();
        DB::table('gallon_exchanges')->insert(['sale_id' => $saleId]);

        $this->assertDatabaseHas('gallon_exchanges', [
            'sale_id' => $saleId,
            'filled_gallons_out' => 0,
            'empty_gallons_returned' => 0,
        ]);
        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('gallon_exchanges')->insert(['sale_id' => $saleId]);
    }

    public function test_updating_a_product_price_preserves_the_sale_item_price(): void
    {
        $saleId = $this->createSale();
        $productId = $this->createProduct();
        $this->createSaleItem($saleId, $productId);

        DB::table('products')->where('id', $productId)->update(['price' => '30.00']);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $saleId,
            'product_id' => $productId,
            'quantity' => 2,
            'unit_price' => '25.00',
            'subtotal' => '50.00',
        ]);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function test_sale_item_quantities_must_be_positive(int $quantity): void
    {
        $saleId = $this->createSale();
        $productId = $this->createProduct();

        $this->expectException(QueryException::class);

        $this->createSaleItem($saleId, $productId, $quantity);
    }

    #[TestWith(['sale_type', 'online'])]
    #[TestWith(['status', 'pending'])]
    public function test_sales_reject_unsupported_types_and_statuses(string $column, string $value): void
    {
        $saleId = $this->createSale();

        $this->expectException(QueryException::class);

        DB::table('sales')->where('id', $saleId)->update([$column => $value]);
    }

    public function test_payments_reject_unsupported_payment_methods(): void
    {
        $saleId = $this->createSale();

        $this->expectException(QueryException::class);

        DB::table('payments')->insert([
            'sale_id' => $saleId,
            'amount' => '50.00',
            'payment_method' => 'unsupported',
            'paid_at' => '2026-10-23 09:00:00',
        ]);
    }

    public function test_customers_with_sales_cannot_be_deleted(): void
    {
        $customerId = DB::table('customers')->insertGetId(['name' => 'Delivery customer']);
        $this->createSale($customerId);

        $this->expectException(QueryException::class);

        DB::table('customers')->where('id', $customerId)->delete();
    }

    public function test_users_with_sales_cannot_be_deleted(): void
    {
        $saleId = $this->createSale();
        $userId = DB::table('sales')->where('id', $saleId)->value('user_id');

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $userId)->delete();
    }

    public function test_products_with_sale_items_cannot_be_deleted(): void
    {
        $saleId = $this->createSale();
        $productId = $this->createProduct();
        $this->createSaleItem($saleId, $productId);

        $this->expectException(QueryException::class);

        DB::table('products')->where('id', $productId)->delete();
    }

    public function test_sales_with_sale_items_cannot_be_deleted(): void
    {
        $saleId = $this->createSale();
        $this->createSaleItem($saleId, $this->createProduct());

        $this->expectException(QueryException::class);

        DB::table('sales')->where('id', $saleId)->delete();
    }

    public function test_sales_with_payments_cannot_be_deleted(): void
    {
        $saleId = $this->createSale();
        DB::table('payments')->insert([
            'sale_id' => $saleId,
            'amount' => '50.00',
            'payment_method' => 'cash',
            'paid_at' => '2026-10-23 09:00:00',
        ]);

        $this->expectException(QueryException::class);

        DB::table('sales')->where('id', $saleId)->delete();
    }

    public function test_sales_with_gallon_exchanges_cannot_be_deleted(): void
    {
        $saleId = $this->createSale();
        DB::table('gallon_exchanges')->insert(['sale_id' => $saleId]);

        $this->expectException(QueryException::class);

        DB::table('sales')->where('id', $saleId)->delete();
    }

    private function createSale(?int $customerId = null): int
    {
        return DB::table('sales')->insertGetId([
            'sale_number' => 'SALE-0001',
            'customer_id' => $customerId,
            'user_id' => User::factory()->create()->id,
            'sale_type' => 'walk_in',
            'total_amount' => '50.00',
            'status' => 'completed',
            'sold_at' => '2026-10-23 09:00:00',
        ]);
    }

    private function createProduct(): int
    {
        return DB::table('products')->insertGetId(['name' => 'Refill', 'price' => '25.00']);
    }

    private function createSaleItem(int $saleId, int $productId, int $quantity = 2): void
    {
        DB::table('sale_items')->insert([
            'sale_id' => $saleId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => '25.00',
            'subtotal' => '50.00',
        ]);
    }
}
