<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', $code)->firstOrFail();

        abort_unless(
            $room->members()->where('user_id', $request->user()->id)->exists(),
            403,
            'You are not a member of this room.'
        );

        $messages = $room->messages()
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $msg) => $this->formatMessage($msg));

        return response()->json($messages);
    }

    public function store(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', $code)->firstOrFail();

        abort_unless(
            $room->members()->where('user_id', $request->user()->id)->exists(),
            403,
            'You are not a member of this room.'
        );

        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $message = $room->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        $message->load('user:id,name');

        broadcast(new MessageSent($message))->toOthers();

        return response()->json($this->formatMessage($message), 201);
    }

    private function formatMessage(Message $message): array
    {
        return [
            'id'         => $message->id,
            'content'    => $message->content,
            'created_at' => $message->created_at->toISOString(),
            'user'       => [
                'id'   => $message->user->id,
                'name' => $message->user->name,
            ],
        ];
    }
}
