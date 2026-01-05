try {
    echo "Starting Pagination Test...\n";

    // 1. Setup User
    $user = \App\Models\User::first();
    if (!$user) {
        $user = \App\Models\User::factory()->create();
    }
    \Illuminate\Support\Facades\Auth::login($user);
    echo "Logged in as User: " . $user->id . "\n";

    // 2. Ensure enough trips exist
    $currentTrips = \App\Models\Trip::whereHas('participants', function($q) use ($user) {
        $q->where('user_id', $user->id);
    })->count();

    if ($currentTrips < 5) {
        echo "Creating trips...\n";
        for ($i = 0; $i < (5 - $currentTrips); $i++) {
            $trip = \App\Models\Trip::create([
                'name' => 'Trip ' . $i,
                'invite_code' => 'PAG' . rand(1000, 9999) . $i,
                'created_by' => $user->id,
                'start_date' => now()
            ]);
            \App\Models\Participant::create([
                'trip_id' => $trip->id,
                'user_id' => $user->id,
                'name' => 'Me'
            ]);
        }
    }

    // 3. Test Controller Logic
    $controller = new \App\Http\Controllers\Api\TripController();

    // Case A: No Limit (Should return all)
    echo "\nTest Case A: No Limit\n";
    $req = new \Illuminate\Http\Request();
    $response = $controller->index($req);
    $data = $response->getData();
    echo "Count: " . count($data) . "\n";

    // Case B: Limit 2
    echo "\nTest Case B: Limit 2\n";
    $req = new \Illuminate\Http\Request(['limit' => 2]);
    $response = $controller->index($req);
    $data = $response->getData();
    echo "Count: " . count($data) . " (Expected 2)\n";

    // Case C: Offset 2, Limit 2
    echo "\nTest Case C: Offset 2, Limit 2\n";
    $req = new \Illuminate\Http\Request(['limit' => 2, 'offset' => 2]);
    $response = $controller->index($req);
    $data = $response->getData();
    echo "Count: " . count($data) . " (Expected 2)\n";

    echo "\nPagination Verified.\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
