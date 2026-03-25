<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Presence channel for chat rooms — returns user info for presence tracking
Broadcast::channel('room.{code}', function (User $user, string $code) {
    $room = Room::where('code', $code)->first();

    if (! $room || ! $room->members()->where('user_id', $user->id)->exists()) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
