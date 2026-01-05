<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Trip;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class TripController extends Controller
{
    public function index(Request $request)
    {
        // Retorna viagens onde o usuário logado é um participante
        $query = Trip::whereHas('participants', function($q) {
            $q->where('user_id', Auth::id());
        })->with('participants')->latest();

        // Paginação Opcional (Limit & Offset)
        if ($request->has('limit')) {
            $limit = (int) $request->input('limit');
            $offset = (int) $request->input('offset', 0); // Default 0
            
            $query->skip($offset)->take($limit);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'string|max:255',
            'members' => 'array', // Nomes de membros extras (sem app)
            'members.*' => 'string'
        ]);

        $trip = Trip::create([
            'name' => $request->name,
            'description' => $request->description,
            'start_date' => now(),
            'invite_code' => strtoupper(Str::random(6)),
            'created_by' => Auth::id()
        ]);

        // 1. Adicionar o Criador como Participante
        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => Auth::id(),
            'name' => Auth::user()->name
        ]);

        // 2. Adicionar membros virtuais (sem user_id)
        if ($request->has('members')) {
            foreach ($request->members as $memberName) {
                Participant::create([
                    'trip_id' => $trip->id,
                    'user_id' => null,
                    'name' => $memberName
                ]);
            }
        }

        return response()->json($trip->load('participants'), 201);
    }

    public function join(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $trip = Trip::where('invite_code', $request->code)->first();

        if (!$trip) {
            return response()->json(['message' => 'Grupo não encontrado.'], 404);
        }

        // Verifica se já participa
        $exists = Participant::where('trip_id', $trip->id)
                             ->where('user_id', Auth::id())
                             ->exists();

        if ($exists) {
            return response()->json(['message' => 'Você já está neste grupo.'], 409);
        }

        Participant::create([
            'trip_id' => $trip->id,
            'user_id' => Auth::id(),
            'name' => Auth::user()->name
        ]);

        return response()->json($trip->load('participants'));
    }
}
