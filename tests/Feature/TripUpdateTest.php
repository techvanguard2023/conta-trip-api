<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Trip;
use App\Models\User;
use App\Models\Participant;

class TripUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_update_trip_name_and_description()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Original Trip Name',
            'description' => 'Original Description',
            'invite_code' => 'TEST1234',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $user->id
        ]);

        // Add user as participant
        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email
        ]);

        $payload = [
            'name' => 'Updated Trip Name',
            'description' => 'Updated Description for the trip'
        ];

        // Act
        $response = $this->putJson("/api/v1/trips/{$trip->id}", $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Updated Trip Name');
        $response->assertJsonPath('description', 'Updated Description for the trip');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'name' => 'Updated Trip Name',
            'description' => 'Updated Description for the trip'
        ]);
    }

    public function test_it_can_update_only_name()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Original Trip Name',
            'description' => 'Original Description',
            'invite_code' => 'TEST1234',
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

        $payload = [
            'name' => 'Updated Trip Name'
        ];

        // Act
        $response = $this->putJson("/api/v1/trips/{$trip->id}", $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Updated Trip Name');
        $response->assertJsonPath('description', 'Original Description');
    }

    public function test_it_can_update_only_description()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Original Trip Name',
            'description' => 'Original Description',
            'invite_code' => 'TEST1234',
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

        $payload = [
            'description' => 'Updated Description'
        ];

        // Act
        $response = $this->putJson("/api/v1/trips/{$trip->id}", $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Original Trip Name');
        $response->assertJsonPath('description', 'Updated Description');
    }

    public function test_it_fails_if_user_is_not_a_participant()
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $trip = Trip::create([
            'name' => 'Original Trip Name',
            'description' => 'Original Description',
            'invite_code' => 'TEST1234',
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

        $payload = [
            'name' => 'Updated Trip Name',
            'description' => 'Updated Description'
        ];

        // Act
        $response = $this->putJson("/api/v1/trips/{$trip->id}", $payload);

        // Assert
        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Você não tem permissão para editar este grupo.');
    }

    public function test_it_validates_name_max_length()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Original Trip Name',
            'description' => 'Original Description',
            'invite_code' => 'TEST1234',
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

        $payload = [
            'name' => str_repeat('a', 256)
        ];

        // Act
        $response = $this->putJson("/api/v1/trips/{$trip->id}", $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_it_validates_description_max_length()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Original Trip Name',
            'description' => 'Original Description',
            'invite_code' => 'TEST1234',
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

        $payload = [
            'description' => str_repeat('a', 501)
        ];

        // Act
        $response = $this->putJson("/api/v1/trips/{$trip->id}", $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('description');
    }

    public function test_it_can_retrieve_trip_details()
    {
        $this->withoutExceptionHandling();

        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'description' => 'Test Description',
            'invite_code' => 'TEST1234',
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

        // Act
        $response = $this->getJson("/api/v1/trips/{$trip->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Test Trip');
        $response->assertJsonPath('description', 'Test Description');
        $response->assertJsonFragment(['name' => $user->name]);
    }

    public function test_it_fails_to_retrieve_trip_if_not_participant()
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'description' => 'Test Description',
            'invite_code' => 'TEST1234',
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

        // Act
        $response = $this->getJson("/api/v1/trips/{$trip->id}");

        // Assert
        $response->assertStatus(403);
    }
}
