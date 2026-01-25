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
            'members' => 'array', // Array de membros extras (sem app)
            'members.*.name' => 'required|string',
            'members.*.email' => 'nullable|email'
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
            'name' => Auth::user()->name,
            'email' => Auth::user()->email
        ]);

        // 2. Adicionar membros virtuais (sem user_id)
        if ($request->has('members')) {
            foreach ($request->members as $member) {
                // Suporta tanto formato antigo (string) quanto novo (objeto)
                if (is_string($member)) {
                    Participant::create([
                        'trip_id' => $trip->id,
                        'user_id' => null,
                        'name' => $member,
                        'email' => null
                    ]);
                } else {
                    Participant::create([
                        'trip_id' => $trip->id,
                        'user_id' => null,
                        'name' => $member['name'],
                        'email' => $member['email'] ?? null
                    ]);
                }
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

    public function destroy($id)
    {
        $trip = Trip::find($id);

        if (!$trip) {
            return response()->json(['message' => 'Grupo não encontrado.'], 404);
        }

        // Verifica se o usuário autenticado é o criador do grupo
        if ($trip->created_by !== Auth::id()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir este grupo.'
            ], 403);
        }

        // Deleta o grupo (cascade irá deletar participantes e despesas relacionadas)
        $trip->delete();

        return response()->json([
            'message' => 'Grupo excluído com sucesso.'
        ], 200);
    }

    public function listPixKeys(Trip $trip)
    {
        // Verificar se o usuário autenticado é um participante do grupo
        $isParticipant = $trip->participants()->where('user_id', Auth::id())->exists();

        if (!$isParticipant) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar as chaves PIX deste grupo.'
            ], 403);
        }

        // Buscar participantes que possuem um usuário associado
        $pixKeys = $trip->participants()
            ->whereNotNull('user_id')
            ->with(['user' => function($query) {
                $query->select('id', 'name', 'pix_key');
            }])
            ->get()
            ->map(function($participant) {
                return [
                    'user_id' => $participant->user_id,
                    'name' => $participant->name,
                    'pix_key' => $participant->user->pix_key ?? null,
                ];
            })
            // Opcional: filtrar apenas quem tem chave pix cadastrada
            ->filter(fn($item) => !empty($item['pix_key']))
            ->values();

        return response()->json($pixKeys);
    }

    public function addParticipant(Request $request, Trip $trip)
    {
        // Verificar se o usuário autenticado é um participante do grupo
        $isParticipant = $trip->participants()->where('user_id', Auth::id())->exists();

        if (!$isParticipant) {
            return response()->json([
                'message' => 'Você não tem permissão para adicionar participantes neste grupo.'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email'
        ]);

        // Verificar se já existe um participante com o mesmo e-mail (se fornecido)
        if ($request->email) {
            $existingParticipant = Participant::where('trip_id', $trip->id)
                ->where('email', $request->email)
                ->first();

            if ($existingParticipant) {
                return response()->json([
                    'message' => 'Já existe um participante com este e-mail neste grupo.'
                ], 409);
            }
        }

        $participant = Participant::create([
            'trip_id' => $trip->id,
            'user_id' => null,
            'name' => $request->name,
            'email' => $request->email
        ]);

        return response()->json([
            'message' => 'Participante adicionado com sucesso.',
            'participant' => $participant
        ], 201);
    }

    public function removeParticipant(Trip $trip, $participantId)
    {
        // Verificar se o usuário autenticado é um participante do grupo
        $isParticipant = $trip->participants()->where('user_id', Auth::id())->exists();

        if (!$isParticipant) {
            return response()->json([
                'message' => 'Você não tem permissão para remover participantes deste grupo.'
            ], 403);
        }

        $participant = Participant::where('id', $participantId)
            ->where('trip_id', $trip->id)
            ->first();

        if (!$participant) {
            return response()->json([
                'message' => 'Participante não encontrado neste grupo.'
            ], 404);
        }

        // Não permitir remover o criador do grupo
        if ($participant->user_id === $trip->created_by) {
            return response()->json([
                'message' => 'Não é possível remover o criador do grupo.'
            ], 403);
        }

        // Verificar se o participante tem despesas associadas
        $hasExpenses = \App\Models\Expense::where('trip_id', $trip->id)
            ->where(function($query) use ($participant) {
                $query->where('payer_id', $participant->id)
                      ->orWhereHas('splits', function($q) use ($participant) {
                          $q->where('participant_id', $participant->id);
                      });
            })
            ->exists();

        if ($hasExpenses) {
            return response()->json([
                'message' => 'Não é possível remover este participante pois ele possui despesas associadas.'
            ], 409);
        }

        $participant->delete();

        return response()->json([
            'message' => 'Participante removido com sucesso.'
        ], 200);
    }
}
