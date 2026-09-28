<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Participant;
use App\Models\Trip;
use App\Models\User;

class ExpenseCreationErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_expense_with_amount_that_does_not_divide_evenly()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'invite_code' => 'TEST1234',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $user->id,
        ]);

        Participant::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'name' => $user->name]);
        $participant1 = Participant::create(['trip_id' => $trip->id, 'name' => 'Member 1']);
        $participant2 = Participant::create(['trip_id' => $trip->id, 'name' => 'Member 2']);

        $payload = [
            'description' => 'Cerveja',
            'amount' => 56.66,
            'payer_id' => $participant1->id,
            'category' => 'drink',
            'splits' => [
                ['memberId' => $participant1->id, 'amount' => 28.33],
                ['memberId' => $participant2->id, 'amount' => 28.33],
            ],
        ];

        $response = $this->postJson("/api/v1/trips/{$trip->id}/expenses", $payload);

        $response->assertStatus(201);
    }
}
