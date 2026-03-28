<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private string $channelCode;
    private string $userName;
    private int $userId;

    private bool $typing;

    /**
     * Create a new event instance.
     */
    public function __construct(string $channelCode, string $userName, int $userId, bool $typing)
    {
        $this->channelCode = $channelCode;
        $this->userName = $userName;
        $this->userId = $userId;
        $this->typing = $typing;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.' . $this->channelCode);
    }

    public function broadcastWith(): array
    {
        return [
            'name' => $this->userName,
            'id' => $this->userId,
            'typing' => $this->typing,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.typing';
    }
}
