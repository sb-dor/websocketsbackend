## Get Started

### Requirements
- PHP 8.2+
- Composer
- MySQL (or any supported database)

### 1. Install dependencies
```bash
composer install
```

### 2. Configure environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials and local IP:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ws_chat
DB_USERNAME=root
DB_PASSWORD=

APP_URL=http://192.168.100.96

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=sync

REVERB_APP_ID=any_numeric_id_you_choose
REVERB_APP_KEY=any_string_you_make_up
REVERB_APP_SECRET=any_secret_you_make_up
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

> **Note on Reverb credentials:**
> - `REVERB_APP_KEY` — a string you make up. Must match `WS_KEY` in the Flutter config. Used by the client to identify which app it is connecting to.
> - `REVERB_APP_SECRET` — a string you make up. **Never shared with Flutter.** When Flutter subscribes to a presence channel, Laravel signs the auth response with this secret (HMAC). Reverb then verifies that signature to confirm the auth response was not forged. It is purely a Laravel ↔ Reverb shared secret.
> - `REVERB_APP_ID` — a numeric identifier for the app inside Reverb. Server-side only.

### 3. Run migrations
```bash
php artisan migrate
```

### 4. Start servers

Open two terminals:

```bash
# Terminal 1 — HTTP API (port 8000)
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 — WebSocket server (port 8080)
php artisan reverb:start --host=0.0.0.0 --port=8080
```

The API is now available at `http://<your-local-ip>:8000` and the WebSocket server at `ws://<your-local-ip>:8080`.

---

## What is Pusher?

[pusher.com](https://pusher.com) is a **paid cloud service** that invented the Pusher WebSocket protocol — the message format, channel naming rules (`private-`, `presence-`), the auth flow, and everything around it. It became so widely adopted that the protocol itself turned into an open standard.

**Laravel Reverb** implements the same Pusher protocol as a self-hosted server. Instead of paying pusher.com to manage the infrastructure, you run your own. Any Pusher-compatible client (like `dart_pusher_channels` in Flutter) works with it out of the box.

Reverb is free because you provide the hardware. Pusher.com charges because they do.

> **How channel authentication works:** Presence channels are private. When Flutter subscribes, Reverb asks it to prove access by hitting `POST /api/broadcasting/auth` with a Bearer token. `Broadcast::routes(['middleware' => ['auth:sanctum']])` in `routes/api.php` registers this endpoint automatically. Laravel verifies the token, checks `routes/channels.php` to decide if the user is allowed, then signs the response with `REVERB_APP_SECRET`. Reverb verifies the signature and allows the subscription.
>
> **Important:** Laravel strips the `presence-` / `private-` prefix before matching `channels.php`. Flutter uses `presence-room.{code}`, so `channels.php` must define `room.{code}` — not `presence-room.{code}`.
>
> **Channel types:**
>
> | | Auth required | Knows who's subscribed | Name prefix |
> |---|---|---|---|
> | Public | No | No | `channel-name` |
> | Private | Yes | No | `private-` |
> | Presence | Yes | Yes | `presence-` |
>
> Private and presence use the exact same auth mechanism. The only difference is what `channels.php` returns: `true/false` for private, a user info array for presence. Returning an array tells Reverb to maintain a live member list and fire `pusher:member_added` / `pusher:member_removed` events.
>
> **The prefix IS the declaration** — the prefix in the channel name is how the client and Reverb determine the channel type. Laravel **strips the prefix** before matching `channels.php`, so `private-room.ABC` and `presence-room.ABC` both match the same base rule:
> ```php
> Broadcast::channel('room.{code}', function ($user, $code) {
>     // return true/false → private channel behaviour
>     // return array      → presence channel behaviour (member info)
> });
> ```
> Public channels need no `channels.php` entry — they require no auth.

> **Why `0.0.0.0`?** Your machine has multiple network interfaces — `127.0.0.1` (loopback, only reachable from the same machine) and your LAN IP e.g. `192.168.x.x` (reachable from other devices on the network). Using `0.0.0.0` tells the server to listen on **all** interfaces at once, so both your machine and other devices (phone, tablet) on the same Wi-Fi can connect. Using `127.0.0.1` would make the server invisible to any other device.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
