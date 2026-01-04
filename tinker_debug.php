

try {
    echo "Starting Debug Script...\n";
    
    // 1. Create User
    $user = \App\Models\User::first();
    if (!$user) {
        $user = \App\Models\User::factory()->create();
    }
    echo "User ID: " . $user->id . "\n";

    // 2. Create Trip
    $trip = \App\Models\Trip::create([
        'name' => 'Debug Trip',
        'invite_code' => 'DBG' . rand(1000, 9999),
        'currency' => 'BRL',
        'start_date' => now(),
        'created_by' => $user->id
    ]);
    echo "Trip ID: " . $trip->id . "\n";

    // 3. Create Participants
    $p1 = \App\Models\Participant::create([
        'trip_id' => $trip->id,
        'name' => 'Member 1',
    ]);
    $p2 = \App\Models\Participant::create([
        'trip_id' => $trip->id,
        'name' => 'Member 2',
    ]);
    echo "Participants: " . $p1->id . ", " . $p2->id . "\n";

    // 4. Attempt to create Expense (Failing Case)
    // Amount 56.66, Splits 28.33 each.
    
    \Illuminate\Support\Facades\DB::beginTransaction();
    try {
        $expense = \App\Models\Expense::create([
            'trip_id' => $trip->id,
            'description' => 'Cerveja Debug',
            'amount' => 56.66,
            'payer_id' => $p1->id,
            'category' => 'drink', // Assuming string works now or enum matches
            'date' => now()
        ]);
        echo "Expense Created: " . $expense->id . "\n";

        \App\Models\ExpenseSplit::create([
            'expense_id' => $expense->id,
            'participant_id' => $p1->id,
            'amount' => 28.33
        ]);
        
        \App\Models\ExpenseSplit::create([
            'expense_id' => $expense->id,
            'participant_id' => $p2->id,
            'amount' => 28.33
        ]);
        
        \Illuminate\Support\Facades\DB::commit();
        echo "SUCCESS: Expense saved successfully.\n";
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        echo "FAILURE: " . $e->getMessage() . "\n";
    }

} catch (\Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}

