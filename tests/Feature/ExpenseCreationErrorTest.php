<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Participant;
use App\Models\Trip;
use App\Models\User;

class ExpenseCreationErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_to_create_expense_with_specific_amount()
    {
        // Arrange
        try {
            $user = User::factory()->create();
            dump('User created: ' . $user->id);

            $trip = Trip::create([
                'name' => 'Test Trip',
                'invite_code' => 'TEST1234',
                'currency' => 'BRL',
                'start_date' => now(),
                'created_by' => $user->id 
            ]);
            dump('Trip created: ' . $trip->id);

            $participant1 = Participant::create([
                'trip_id' => $trip->id,
                'name' => 'Member 1',
            ]);
            dump('Participant 1 created: ' . $participant1->id);

            $participant2 = Participant::create([
                'trip_id' => $trip->id,
                'name' => 'Member 2',
            ]);
            dump('Participant 2 created: ' . $participant2->id);

        } catch (\Exception $e) {
            dd('SETUP FAILED: ' . $e->getMessage());
        }

        $payload = [
            "description" => "Cerveja",
            "amount" => 56.66,
            "payer_id" => $participant1->id,
            "category" => "drink",
            "splits" => [
                [
                    "memberId" => $participant1->id,
                    "amount" => 28.33
                ],
                [
                    "memberId" => $participant2->id,
                    "amount" => 28.33
                ]
            ]
        ];

        // Act
        $response = $this->postJson("/api/trips/{$trip->id}/expenses", $payload);

        if ($response->status() !== 201) {
            $response->dump();
        }

        // Assert
        $response->assertStatus(201); // Expectation is it SHOULD work
    }
}
