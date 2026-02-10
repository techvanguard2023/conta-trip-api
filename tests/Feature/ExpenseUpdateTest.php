<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Participant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseSplit;

class ExpenseUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_update_an_expense()
    {
        $this->withoutExceptionHandling();
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'invite_code' => 'TEST1234',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $user->id 
        ]);

        $participant1 = Participant::create(['trip_id' => $trip->id, 'name' => 'Member 1']);
        $participant2 = Participant::create(['trip_id' => $trip->id, 'name' => 'Member 2']);

        $expense = Expense::create([
            'trip_id' => $trip->id,
            'description' => 'Original Description',
            'amount' => 100.00,
            'payer_id' => $participant1->id,
            'category' => 'food',
            'date' => now()
        ]);

        ExpenseSplit::create(['expense_id' => $expense->id, 'participant_id' => $participant1->id, 'amount' => 50.00]);
        ExpenseSplit::create(['expense_id' => $expense->id, 'participant_id' => $participant2->id, 'amount' => 50.00]);

        $payload = [
            "description" => "Updated Description",
            "amount" => 120.00,
            "payer_id" => $participant2->id,
            "category" => "transport",
            "splits" => [
                [
                    "memberId" => $participant1->id,
                    "amount" => 60.00
                ],
                [
                    "memberId" => $participant2->id,
                    "amount" => 60.00
                ]
            ]
        ];

        // Act
        $response = $this->putJson("/api/v1/expenses/{$expense->id}", $payload);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('description', 'Updated Description');
        $response->assertJsonPath('amount', 120.00);
        $response->assertJsonCount(2, 'splits');

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'description' => 'Updated Description',
            'amount' => 120.00,
            'payer_id' => $participant2->id
        ]);

        $this->assertDatabaseHas('expense_splits', [
            'expense_id' => $expense->id,
            'amount' => 60.00
        ]);
        
        $this->assertEquals(2, ExpenseSplit::where('expense_id', $expense->id)->count());
    }

    public function test_it_fails_update_if_splits_do_not_match_amount()
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'invite_code' => 'TEST1234',
            'currency' => 'BRL',
            'start_date' => now(),
            'created_by' => $user->id 
        ]);

        $participant1 = Participant::create(['trip_id' => $trip->id, 'name' => 'Member 1']);

        $expense = Expense::create([
            'trip_id' => $trip->id,
            'description' => 'Original Description',
            'amount' => 100.00,
            'payer_id' => $participant1->id,
            'category' => 'food',
            'date' => now()
        ]);

        $payload = [
            "description" => "Updated Description",
            "amount" => 100.00,
            "payer_id" => $participant1->id,
            "category" => "food",
            "splits" => [
                [
                    "memberId" => $participant1->id,
                    "amount" => 90.00 // Sum is 90, expected 100
                ]
            ]
        ];

        // Act
        $response = $this->putJson("/api/v1/expenses/{$expense->id}", $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'A soma das divisões não corresponde ao valor total da despesa']);
    }
}
