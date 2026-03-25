<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rooms = $request->user()
            ->rooms()
            ->with('owner:id,name')
            ->latest()
            ->get()
            ->map(fn (Room $room) => $this->formatRoom($room));

        return response()->json($rooms);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $room = Room::create([
            'name'     => $data['name'],
            'owner_id' => $request->user()->id,
        ]);

        // creator automatically joins the room
        $room->members()->attach($request->user()->id);

        return response()->json($this->formatRoom($room), 201);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:8'],
        ]);

        $room = Room::where('code', strtoupper($data['code']))->firstOrFail();

        // idempotent — attach only if not already a member
        $room->members()->syncWithoutDetaching([$request->user()->id]);

        return response()->json($this->formatRoom($room));
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', $code)->firstOrFail();

        // ensure the requesting user is a member
        abort_unless(
            $room->members()->where('user_id', $request->user()->id)->exists(),
            403,
            'You are not a member of this room.'
        );

        return response()->json($this->formatRoom($room));
    }

    private function formatRoom(Room $room): array
    {
        return [
            'id'       => $room->id,
            'code'     => $room->code,
            'name'     => $room->name,
            'owner_id' => $room->owner_id,
        ];
    }
}
