<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    #[TestWith(['sale'])]
    #[TestWith(['payment'])]
    #[TestWith(['reversal'])]
    public function test_accounts_linked_to_financial_history_cannot_be_deleted_or_logged_out(string $recordType): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $sale = DB::table('sales')->insertGetId([
            'sale_number' => 'PROTECTED', 'user_id' => $recordType === 'sale' ? $user->id : $otherUser->id,
            'sale_type' => 'walk_in', 'total_amount' => '100.00', 'status' => 'completed', 'sold_at' => now(),
        ]);
        $payment = DB::table('payments')->insertGetId([
            'sale_id' => $sale, 'user_id' => $recordType === 'payment' ? $user->id : $otherUser->id,
            'amount' => '20.00', 'payment_method' => 'cash', 'paid_at' => now(),
        ]);
        if ($recordType === 'reversal') {
            DB::table('payment_reversals')->insert([
                'payment_id' => $payment, 'user_id' => $user->id, 'reason' => 'Protected history', 'reversed_at' => now(),
            ]);
        }

        $this->actingAs($user)->from(route('profile.edit'))->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrors([
                'password' => 'Accounts linked to sales, payments, or reversals cannot be deleted. Financial history must be preserved.',
            ]);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('sales', ['id' => $sale]);
        $this->assertDatabaseHas('payments', ['id' => $payment]);
    }
}
