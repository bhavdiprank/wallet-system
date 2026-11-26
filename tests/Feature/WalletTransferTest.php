<?php
//php artisan test --filter=WalletTransferTest
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\{User, Wallet, WalletTransaction};
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithWallet(int $balance): User
    {
        $user = User::factory()->create();

        $user->wallet()->create([
            'balance' => $balance,
        ]);

        return $user;
    }

    public function test_successful_transfer(): void
    {
        $sender = $this->createUserWithWallet(100000);
        $receiver = $this->createUserWithWallet(0);

        $response = $this->actingAs($sender)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Idempotency-Key', 'transfer-001')
            ->postJson('/api/v1/wallet/transfer', [
                'email' => $receiver->email,
                'amount_paise' => 25000,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $sender->id,
            'balance' => 75000,
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $receiver->id,
            'balance' => 25000,
        ]);

        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_insufficient_balance(): void
    {
        $sender = $this->createUserWithWallet(10000);
        $receiver = $this->createUserWithWallet(0);

        $response = $this->actingAs($sender)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Idempotency-Key', 'transfer-002')
            ->postJson('/api/v1/wallet/transfer', [
                'email' => $receiver->email,
                'amount_paise' => 20000,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $sender->id,
            'balance' => 10000,
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $receiver->id,
            'balance' => 0,
        ]);

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_user_cannot_transfer_to_self(): void
    {
        $sender = $this->createUserWithWallet(100000);

        $response = $this->actingAs($sender)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Idempotency-Key', 'transfer-003')
            ->postJson('/api/v1/wallet/transfer', [
                'email' => $sender->email,
                'amount_paise' => 1000,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $sender->id,
            'balance' => 100000,
        ]);
    }

    public function test_same_idempotency_key_does_not_transfer_twice(): void
    {
        $sender = $this->createUserWithWallet(100000);
        $receiver = $this->createUserWithWallet(0);

        $payload = [
            'email' => $receiver->email,
            'amount_paise' => 25000,
        ];

        $first = $this->actingAs($sender)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Idempotency-Key', 'transfer-004')
            ->postJson('/api/v1/wallet/transfer', $payload);

        $second = $this->actingAs($sender)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Idempotency-Key', 'transfer-004')
            ->postJson('/api/v1/wallet/transfer', $payload);

        $first->assertOk();
        $second->assertOk();

        $this->assertEquals(
            $first->json('data.reference_id'),
            $second->json('data.reference_id')
        );

        $this->assertDatabaseHas('wallets', [
            'user_id' => $sender->id,
            'balance' => 75000,
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $receiver->id,
            'balance' => 25000,
        ]);

        $this->assertDatabaseCount('wallet_transactions', 2);
    }
}
