<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Participant;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetroactiveInclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_member_included_retroactively_in_equal_split_expenses()
    {
        // 1. Arrange: Trip with 2 members and 1 equal expense
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create([
            'name' => 'Trip',
            'invite_code' => 'TEST12',
            'start_date' => now(),
            'created_by' => $user->id
        ]);

        $p1 = Participant::create(['trip_id' => $trip->id, 'name' => 'P1']);
        $p2 = Participant::create(['trip_id' => $trip->id, 'name' => 'P2']);

        $expense = Expense::create([
            'trip_id' => $trip->id,
            'description' => 'Pizza',
            'amount' => 100.00,
            'payer_id' => $p1->id,
            'category' => 'Food',
            'date' => now(),
            'split_type' => 'equal'
        ]);

        ExpenseSplit::create(['expense_id' => $expense->id, 'participant_id' => $p1->id, 'amount' => 50.00]);
        ExpenseSplit::create(['expense_id' => $expense->id, 'participant_id' => $p2->id, 'amount' => 50.00]);

        // 2. Act: 3rd member joins with include_retroactive = true
        $response = $this->postJson("/api/v1/trips/{$trip->id}/participants", [
            'name' => 'P3',
            'include_retroactive' => true
        ]);

        // 3. Assert
        $response->assertStatus(201);
        
        $this->assertEquals(3, ExpenseSplit::where('expense_id', $expense->id)->count());
        
        // Each should have 33.33, except one that gets the rounding diff (100 - 33.33*2 = 33.34)
        $p3Split = ExpenseSplit::where('expense_id', $expense->id)->where('participant_id', $response->json('participant.id'))->first();
        $this->assertNotNull($p3Split);
        
        $splitsTotal = ExpenseSplit::where('expense_id', $expense->id)->sum('amount');
        $this->assertEquals(100.00, $splitsTotal);
    }

    public function test_new_member_not_included_in_custom_split_expenses()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $trip = Trip::create(['name' => 'Trip', 'invite_code' => 'T1', 'start_date' => now(), 'created_by' => $user->id]);
        $p1 = Participant::create(['trip_id' => $trip->id, 'name' => 'P1']);

        $expense = Expense::create([
            'trip_id' => $trip->id,
            'description' => 'Custom',
            'amount' => 100.00,
            'payer_id' => $p1->id,
            'category' => 'Food',
            'date' => now(),
            'split_type' => 'custom'
        ]);

        ExpenseSplit::create(['expense_id' => $expense->id, 'participant_id' => $p1->id, 'amount' => 100.00]);

        // Act
        $this->postJson("/api/v1/trips/{$trip->id}/participants", [
            'name' => 'P2',
            'include_retroactive' => true
        ]);

        // Assert: Still only 1 split
        $this->assertEquals(1, ExpenseSplit::where('expense_id', $expense->id)->count());
    }
}
