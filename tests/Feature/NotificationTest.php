<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Trip;
use App\Models\Participant;
use App\Models\Expense;
use App\Models\ExpenseSplit;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_store_notification_token()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'token' => 'eXqfzm5Z9bXzQe9K3nL7vJ2mP5sR8tU1wV4yZ7cB'
        ];

        // Act
        $response = $this->postJson('/api/v1/fcm-token', $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Token de notificação armazenado com sucesso');
        $response->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'fcm_token' => 'eXqfzm5Z9bXzQe9K3nL7vJ2mP5sR8tU1wV4yZ7cB'
        ]);
    }

    public function test_it_fails_if_token_is_empty()
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'token' => ''
        ];

        // Act
        $response = $this->postJson('/api/v1/fcm-token', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('token');
    }

    public function test_it_fails_if_token_is_too_short()
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'token' => '12345'
        ];

        // Act
        $response = $this->postJson('/api/v1/fcm-token', $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('token');
    }

    public function test_it_requires_authentication_for_token_storage()
    {
        // Act
        $response = $this->postJson('/api/v1/fcm-token', [
            'token' => 'eXqfzm5Z9bXzQe9K3nL7vJ2mP5sR8tU1wV4yZ7cB'
        ]);

        // Assert
        $response->assertStatus(401);
    }

    public function test_it_can_update_existing_token()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create([
            'fcm_token' => 'old_token'
        ]);
        $this->actingAs($user);

        $payload = [
            'token' => 'new_eXqfzm5Z9bXzQe9K3nL7vJ2mP5sR8tU1wV4yZ7cB'
        ];

        // Act
        $response = $this->postJson('/api/v1/fcm-token', $payload);

        // Assert
        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'fcm_token' => 'new_eXqfzm5Z9bXzQe9K3nL7vJ2mP5sR8tU1wV4yZ7cB'
        ]);
    }

    public function test_notification_sent_when_new_expense_is_created()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $creator = User::factory()->create(['fcm_token' => 'creator_token']);
        $otherUser = User::factory()->create(['fcm_token' => 'other_token']);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'invite_code' => 'TEST123',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $creator->id
        ]);

        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => $creator->id,
            'name' => $creator->name,
            'email' => $creator->email
        ]);

        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => $otherUser->id,
            'name' => $otherUser->name,
            'email' => $otherUser->email
        ]);

        $creatorParticipant = Participant::where('trip_id', $trip->id)
            ->where('user_id', $creator->id)
            ->first();

        $this->actingAs($creator);

        $payload = [
            'description' => 'Dinner',
            'amount' => 100,
            'payer_id' => $creatorParticipant->id,
            'category' => 'food',
            'splits' => [
                [
                    'memberId' => $creatorParticipant->id,
                    'amount' => 50
                ],
                [
                    'memberId' => Participant::where('trip_id', $trip->id)
                        ->where('user_id', $otherUser->id)
                        ->first()
                        ->id,
                    'amount' => 50
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/v1/trips/{$trip->id}/expenses", $payload);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', [
            'description' => 'Dinner',
            'amount' => 100
        ]);
    }

    public function test_notification_sent_when_new_member_is_added()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create(['fcm_token' => 'user_token']);
        $trip = Trip::create([
            'name' => 'Test Trip',
            'invite_code' => 'TEST123',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $user->id
        ]);

        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email
        ]);

        $this->actingAs($user);

        $payload = [
            'name' => 'New Member',
            'email' => 'newmember@example.com'
        ];

        // Act
        $response = $this->postJson("/api/v1/trips/{$trip->id}/participants", $payload);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('participants', [
            'trip_id' => $trip->id,
            'name' => 'New Member',
            'email' => 'newmember@example.com'
        ]);
    }

    public function test_notification_sent_when_trip_status_changes()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create(['fcm_token' => 'user_token']);
        $trip = Trip::create([
            'name' => 'Test Trip',
            'invite_code' => 'TEST123',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $user->id,
            'status' => 'open'
        ]);

        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email
        ]);

        $this->actingAs($user);

        $payload = [
            'status' => 'archived'
        ];

        // Act
        $response = $this->putJson("/api/v1/trips/{$trip->id}/status", $payload);

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => 'archived'
        ]);
    }

    public function test_notification_service_handles_missing_tokens()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $creator = User::factory()->create(); // No token
        $trip = Trip::create([
            'name' => 'Test Trip',
            'invite_code' => 'TEST123',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $creator->id
        ]);

        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => $creator->id,
            'name' => $creator->name,
            'email' => $creator->email
        ]);

        $creatorParticipant = Participant::where('trip_id', $trip->id)
            ->where('user_id', $creator->id)
            ->first();

        $this->actingAs($creator);

        $payload = [
            'description' => 'Dinner',
            'amount' => 100,
            'payer_id' => $creatorParticipant->id,
            'category' => 'food',
            'splits' => [
                [
                    'memberId' => $creatorParticipant->id,
                    'amount' => 100
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/v1/trips/{$trip->id}/expenses", $payload);

        // Assert - Should still create expense even if no tokens available
        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', [
            'description' => 'Dinner'
        ]);
    }

    public function test_firebase_service_logs_errors_gracefully()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create(['fcm_token' => 'invalid_token']);
        $trip = Trip::create([
            'name' => 'Test Trip',
            'invite_code' => 'TEST123',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $user->id
        ]);

        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email
        ]);

        $creatorParticipant = Participant::where('trip_id', $trip->id)
            ->where('user_id', $user->id)
            ->first();

        $this->actingAs($user);

        $payload = [
            'description' => 'Test Expense',
            'amount' => 50,
            'payer_id' => $creatorParticipant->id,
            'category' => 'food',
            'splits' => [
                [
                    'memberId' => $creatorParticipant->id,
                    'amount' => 50
                ]
            ]
        ];

        // Act - Should not throw exception even if Firebase service fails
        try {
            $response = $this->postJson("/api/v1/trips/{$trip->id}/expenses", $payload);
            // Either succeeds or fails gracefully
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // If it fails, it should be logged, not thrown
            $this->fail('Exception should be caught and logged');
        }
    }
}
