# Laravel Reverb — Step-by-Step Setup Guide

This document describes every step taken to build this real-time chat backend using
Laravel 12, Laravel Reverb (WebSocket server), and Laravel Sanctum (API authentication).

---

## 1. Create a new Laravel project

```bash
composer create-project laravel/laravel ws-chat
cd ws-chat
```

---

## 2. Install Laravel Reverb

Reverb is Laravel's first-party WebSocket server. Install it via Artisan:

```bash
php artisan install:broadcasting
```

When prompted, choose **Reverb** as the broadcaster.

This command:
- Adds `laravel/reverb` to `composer.json` and runs `composer require` automatically
- Publishes `config/reverb.php`
- Adds Reverb environment variables to `.env`
- Enables the `BroadcastServiceProvider` in `bootstrap/providers.php`

> If you prefer to install manually: `composer require laravel/reverb` then
> `php artisan reverb:install`.

---

## 3. Install Laravel Sanctum

Sanctum provides token-based API authentication (Bearer tokens).

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

Add `HasApiTokens` to the `User` model:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}
```

---

## 4. Configure `.env`

Set the broadcast driver to `reverb` and fill in the Reverb credentials that
`install:broadcasting` generated. Also set `APP_URL` to your machine's LAN IP so
mobile devices on the same network can reach the server.

```env
APP_URL=http://192.168.100.96        # your LAN IP (not localhost — mobile needs this)

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=ANY_INT_ID            # better with 6 length
REVERB_APP_KEY=ANY_STRING_KEY
REVERB_APP_SECRET=ANY_STRING_SECRET
REVERB_HOST="127.0.0.1"             # Reverb listens on localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

> **Why `127.0.0.1` for `REVERB_HOST`?**
> Reverb itself listens on `0.0.0.0:8080` (all interfaces). `REVERB_HOST` in `.env`
> is what the *Laravel application* uses to reach Reverb when dispatching events.
> Setting it to `127.0.0.1` keeps event dispatch on loopback, while the Flutter client
> connects directly to your LAN IP:8080.

---

## 5. Create the database

```bash
# In MySQL / MariaDB
CREATE DATABASE websockets;
```

Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=websockets
DB_USERNAME=root
DB_PASSWORD=
```

---

## 6. Create migrations

### rooms table

```bash
php artisan make:migration create_rooms_table
```

```php
Schema::create('rooms', function (Blueprint $table) {
    $table->id();
    $table->string('code', 8)->unique();   // random 8-char join code
    $table->string('name');
    $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
});
```

### room_user pivot table

```bash
php artisan make:migration create_room_user_table
```

```php
Schema::create('room_user', function (Blueprint $table) {
    $table->foreignId('room_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->primary(['room_id', 'user_id']);
});
```

### messages table

```bash
php artisan make:migration create_messages_table
```

```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('room_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('content');
    $table->timestamps();
});
```

Run all migrations:

```bash
php artisan migrate
```

---

## 7. Create Models

### Room model

`app/Models/Room.php` — auto-generates a random 8-character uppercase join code
on creation, and defines `owner`, `members` (many-to-many), and `messages` relations:

```php
protected static function boot(): void
{
    parent::boot();
    static::creating(function (Room $room) {
        $room->code = strtoupper(Str::random(8));
    });
}

public function owner(): BelongsTo     { return $this->belongsTo(User::class, 'owner_id'); }
public function members(): BelongsToMany { return $this->belongsToMany(User::class); }
public function messages(): HasMany    { return $this->hasMany(Message::class); }
```

### Message model

`app/Models/Message.php` — belongs to a `Room` and a `User`:

```php
protected $fillable = ['room_id', 'user_id', 'content'];

public function room(): BelongsTo { return $this->belongsTo(Room::class); }
public function user(): BelongsTo { return $this->belongsTo(User::class); }
```

---

## 8. Create broadcast events

### MessageSent

```bash
php artisan make:event MessageSent
```

`app/Events/MessageSent.php` — broadcasts on a **Presence channel** so Reverb
tracks which users are in the room:

```php
class MessageSent implements ShouldBroadcast
{
    public function __construct(public readonly Message $message) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.' . $this->message->room->code);
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->message->id,
            'content'    => $this->message->content,
            'created_at' => $this->message->created_at->toISOString(),
            'user'       => ['id' => $this->message->user->id, 'name' => $this->message->user->name],
        ];
    }

    public function broadcastAs(): string { return 'message.sent'; }
}
```

### TypingEvent

```bash
php artisan make:event TypingEvent
```

`app/Events/TypingEvent.php` — broadcasts a typing indicator on the same Presence channel:

```php
class TypingEvent implements ShouldBroadcast
{
    public function __construct(
        private string $channelCode,
        private string $userName,
        private int    $userId,
        private bool   $typing,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('room.' . $this->channelCode);
    }

    public function broadcastWith(): array
    {
        return ['name' => $this->userName, 'id' => $this->userId, 'typing' => $this->typing];
    }

    public function broadcastAs(): string { return 'message.typing'; }
}
```

---

## 9. Create API controllers

```bash
php artisan make:controller Api/AuthController
php artisan make:controller Api/RoomController
php artisan make:controller Api/MessageController
```

### AuthController

Handles register, login (returns Sanctum token), logout, and `me` endpoint.
On login it calls `$user->createToken('app')->plainTextToken` and returns the token
in the response body — the Flutter client stores this and sends it as
`Authorization: Bearer <token>` on every subsequent request.

### RoomController

| Method | Action |
|--------|--------|
| `index` | Returns all rooms the authenticated user is a member of |
| `store` | Creates a room with a random `code`, attaches creator as a member |
| `join`  | Finds room by `code`, attaches the user (idempotent — `syncWithoutDetaching`) |
| `show`  | Returns room details (403 if not a member) |

### MessageController

| Method | Action |
|--------|--------|
| `index`      | Returns last 50 messages for the room, oldest-first |
| `store`      | Creates a message, fires `broadcast(new MessageSent($message))` |
| `typing`     | Fires `broadcast(new TypingEvent($code, $name, $id, true))` |
| `stopTyping` | Fires `broadcast(new TypingEvent($code, $name, $id, false))` |

---

## 10. Register API routes

`routes/api.php` — two important things here:

1. `Broadcast::routes(['middleware' => ['auth:sanctum']])` — overrides the default
   broadcasting auth route to accept Sanctum Bearer tokens instead of web sessions.
   Without this, the `/broadcasting/auth` endpoint rejects mobile clients.

2. `require base_path('routes/channels.php')` — loads channel authorization callbacks
   inside the API route group so they are registered.

```php
Broadcast::routes(['middleware' => ['auth:sanctum']]);
require base_path('routes/channels.php');

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    Route::get('/rooms',          [RoomController::class, 'index']);
    Route::post('/rooms',         [RoomController::class, 'store']);
    Route::post('/rooms/join',    [RoomController::class, 'join']);
    Route::get('/rooms/{code}',   [RoomController::class, 'show']);

    Route::get('/rooms/{code}/messages',     [MessageController::class, 'index']);
    Route::post('/rooms/{code}/messages',    [MessageController::class, 'store']);
    Route::post('/rooms/{code}/typing',      [MessageController::class, 'typing']);
    Route::post('/rooms/{code}/stop-typing', [MessageController::class, 'stopTyping']);
});
```

---

## 11. Authorize the Presence channel

`routes/channels.php` — the callback runs when a client subscribes to
`room.{code}`. It checks that the user is a member and returns their info (name + id)
for presence tracking. Returning `false` rejects the subscription.

```php
Broadcast::channel('room.{code}', function (User $user, string $code) {
    $room = Room::where('code', $code)->first();

    if (! $room || ! $room->members()->where('user_id', $user->id)->exists()) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
```

---

## 12. Run the servers

Two processes must be running simultaneously:

```bash
# Terminal 1 — Laravel HTTP server (accessible on LAN)
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 — Reverb WebSocket server
php artisan reverb:start --host=0.0.0.0 --port=8080
```

| Process | Host | Port | Purpose |
|---------|------|------|---------|
| `artisan serve` | `0.0.0.0` | `8000` | REST API + broadcasting auth |
| `reverb:start`  | `0.0.0.0` | `8080` | WebSocket connections |

> `--host=0.0.0.0` makes both servers reachable from other devices on the LAN
> (phones, simulators on a different OS). Without it they only accept connections
> from `localhost`.

---

## 13. Flutter client configuration

The Flutter app uses two base URLs configured via `--dart-define-from-file`:

```json
// config/production.json
{
  "API_BASE_URL": "http://192.168.100.96:8000",
  "WS_HOST": "192.168.100.96",
  "WS_PORT": "8080"
}
```

Run the Flutter app with:

```bash
flutter run --dart-define-from-file=config/production.json
```

The client connects to Reverb using the Pusher protocol (Reverb is Pusher-compatible)
and subscribes to `presence-room.{code}` channels after joining a room.

---

## How real-time messaging works (end-to-end)

```
Flutter client
  │
  ├─► POST /rooms/{code}/messages        (HTTP — sends message)
  │       └─ MessageController::store()
  │               └─ broadcast(new MessageSent($message))
  │                       └─► Reverb WebSocket server
  │                               └─► all clients subscribed to presence-room.{code}
  │                                       └─► Flutter receives 'message.sent' event
  │
  ├─► POST /rooms/{code}/typing          (HTTP — user started typing)
  │       └─ broadcast(new TypingEvent(..., typing: true))
  │               └─► Reverb → other clients receive 'message.typing'
  │
  └─► POST /rooms/{code}/stop-typing     (HTTP — user stopped typing)
          └─ broadcast(new TypingEvent(..., typing: false))
```

---

## Quick reference — all artisan commands used

```bash
composer create-project laravel/laravel ws-chat
php artisan install:broadcasting        # installs Reverb, publishes config
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

php artisan make:migration create_rooms_table
php artisan make:migration create_room_user_table
php artisan make:migration create_messages_table

php artisan make:event MessageSent
php artisan make:event TypingEvent

php artisan make:controller Api/AuthController
php artisan make:controller Api/RoomController
php artisan make:controller Api/MessageController

php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start --host=0.0.0.0 --port=8080
```
