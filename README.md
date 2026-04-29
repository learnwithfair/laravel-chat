# Laravel Chat

A professional, real-time chat package for Laravel 11/12.

Supports private and group conversations, message reactions, file attachments, message status tracking (sent / delivered / seen), typing indicators, user blocking, group management, FCM push notifications, and WebSocket broadcasting via Laravel Reverb or Pusher.

---

## Requirements

| Dependency         | Version       |
|--------------------|---------------|
| PHP                | 8.2 or higher |
| Laravel            | 11.x or 12.x  |
| Laravel Sanctum    | 4.x           |
| Laravel Reverb     | 1.x (optional, for Reverb driver) |
| kreait/laravel-firebase | 5.x (optional, for push notifications) |

---

## Installation

### Step 1 — Install via Composer

```bash
composer require rahatulrabbi/laravel-chat
```

### Step 2 — Run the install wizard

```bash
php artisan chat:install
```

The wizard will:
- Publish the config file to `config/laravel-chat.php`
- Publish all database migrations
- Publish stubs for customization
- Ask which broadcasting driver you want (Reverb or Pusher)
- Write the required `.env` variables
- Optionally run migrations
- Optionally enable FCM push notifications

### Step 3 — Register middleware alias

Open `bootstrap/app.php` and add the alias inside `withMiddleware`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'last_seen' => \RahatulRabbi\LaravelChat\Http\Middleware\UpdateLastSeen::class,
    ]);
})
```

### Step 4 — Add the scheduler

Inside `bootstrap/app.php` add the schedule for auto-unmute:

```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('chat:auto-unmute')->everyMinute();
})
```

Then add the cron entry on your server:

```bash
* * * * * php /path/to/your/app/artisan schedule:run >> /dev/null 2>&1
```

### Step 5 — Add chat relations to your User model

Add the following relationships and helpers to your `App\Models\User`:

```php
// Blocking
public function blockedUsers()   { return $this->belongsToMany(User::class, 'user_blocks', 'user_id', 'blocked_id')->withTimestamps(); }
public function blockedByUsers() { return $this->belongsToMany(User::class, 'user_blocks', 'blocked_id', 'user_id')->withTimestamps(); }
public function hasBlocked(User $user): bool { return $this->blockedUsers()->where('users.id', $user->id)->exists(); }
public function isBlockedBy(User $user): bool { return $this->blockedByUsers()->where('users.id', $user->id)->exists(); }

// Restricting
public function restrictedUsers()   { return $this->belongsToMany(User::class, 'user_restricts', 'user_id', 'restricted_id')->withTimestamps(); }
public function restrictedByUsers() { return $this->belongsToMany(User::class, 'user_restricts', 'restricted_id', 'user_id')->withTimestamps(); }

// Device tokens (for push notifications)
public function deviceTokens() { return $this->hasMany(\RahatulRabbi\LaravelChat\Models\DeviceToken::class); }

// Online check
public function isOnline(): bool
{
    $field     = config('laravel-chat.user_fields.last_seen', 'last_seen_at');
    $threshold = config('laravel-chat.online_threshold_minutes', 2);
    return $this->{$field} && $this->{$field}->greaterThan(now()->subMinutes($threshold));
}
```

### Step 6 — Run migrations

```bash
php artisan migrate
```

### Step 7 — Start WebSocket server

```bash
php artisan reverb:start --debug
```

---

## Configuration

After publishing, open `config/laravel-chat.php` and review every section.

### User field mapping

If your users table uses different column names, update the mapping:

```php
'user_fields' => [
    'id'        => 'id',
    'name'      => 'name',
    'avatar'    => 'profile_photo',   // your column
    'last_seen' => 'last_activity_at', // your column
],
```

### Routing

To disable the built-in routes and register your own:

```php
'routing' => [
    'enabled' => false,
],
```

Then copy `routes/api.php` from the package into your project and register it manually.

### Broadcasting driver

```php
'broadcasting' => [
    'driver' => env('BROADCAST_DRIVER', 'reverb'), // or 'pusher'
],
```

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan chat:install` | Interactive installation wizard |
| `php artisan chat:uninstall` | Remove published files and optionally drop tables |
| `php artisan chat:publish --tag=config` | Publish only the config |
| `php artisan chat:publish --tag=migrations` | Publish only migrations |
| `php artisan chat:publish --tag=stubs` | Publish stubs for customization |
| `php artisan chat:auto-unmute` | Queue expired mute jobs (run via scheduler) |

---

## API Endpoints

All endpoints are prefixed with `/api/v1` and require authentication.

### Conversations

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/conversations` | List all conversations |
| POST   | `/conversations` | Create group |
| POST   | `/conversations/private` | Start private conversation |
| DELETE | `/conversations/{id}` | Remove conversation for self |
| GET    | `/conversations/{id}/media` | Media library |

### Messages

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/messages` | Send message |
| GET    | `/messages/{conversation}` | Get messages |
| PUT    | `/messages/{message}` | Edit message |
| DELETE | `/messages/delete-for-me` | Delete for self |
| DELETE | `/messages/delete-for-everyone` | Unsend for all |
| GET    | `/messages/seen/{conversation}` | Mark all seen |
| POST   | `/messages/mark-seen` | Mark specific messages seen |
| GET    | `/messages/delivered/{conversation}` | Mark delivered |
| POST   | `/messages/{message}/forward` | Forward message |
| POST   | `/messages/{message}/toggle-pin` | Pin / unpin |
| GET    | `/messages/{conversation}/pinned-messages` | Get pinned |

### Reactions

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/messages/{message}/reaction` | Toggle reaction |
| GET    | `/messages/{message}/reaction` | List reactions |

### Group Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/group/{id}/update` | Update group info |
| POST   | `/group/{id}/members/add` | Add members |
| POST   | `/group/{id}/members/remove` | Remove members |
| GET    | `/group/{id}/members` | List members |
| POST   | `/group/{id}/admins/add` | Add admins |
| POST   | `/group/{id}/admins/remove` | Remove admins |
| POST   | `/group/{id}/mute` | Mute / unmute |
| POST   | `/group/{id}/leave` | Leave group |
| DELETE | `/group/{id}/delete-group` | Delete group |
| POST   | `/group/{id}/regenerate-invite` | New invite link |
| GET    | `/accept-invite/{token}` | Accept invite |

### Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/available-users` | Search users |
| GET    | `/online-users` | Online users |
| POST   | `/users/{user}/block-toggle` | Block / unblock |
| POST   | `/users/{user}/restrict-toggle` | Restrict / unrestrict |

---

## Real-Time Events

### ConversationEvent

Broadcast on `user.{userId}` (private) or `conversation.{id}` (presence).

Actions: `added`, `removed`, `left`, `updated`, `deleted`, `blocked`, `unblocked`, `unmuted`, `member_added`, `member_left`, `admin_added`, `admin_removed`

### MessageEvent

Broadcast on `conversation.{id}` (presence).

Types: `sent`, `updated`, `deleted_for_everyone`, `deleted_permanent`, `reaction`, `delivered`, `seen`, `pinned`, `unpinned`

---

## Frontend Integration — Vue 3

### Install dependencies

```bash
npm install laravel-echo pusher-js
```

### echo.js (Reverb)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;
window.axios  = axios;
axios.defaults.withCredentials = true;

const wsHost  = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
const port    = Number(import.meta.env.VITE_REVERB_PORT ?? 80);
const forceTLS = (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key:         import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort:      port,
    wssPort:     port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
});
```

Import in `resources/js/app.js`:

```javascript
import './echo';
```

### Subscribe to channels

```javascript
// Global online presence
window.Echo.join('online')
    .here(users  => { onlineUsers.value = users; })
    .joining(user => { onlineUsers.value.push(user); })
    .leaving(user => { onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id); });

// Personal notifications
window.Echo.private(`user.${userId}`)
    .listen('.ConversationEvent', event => handleConversationEvent(event));

// Conversation messages
window.Echo.join(`conversation.${conversationId}`)
    .listen('.MessageEvent', event => handleMessageEvent(event))
    .listen('.ConversationEvent', event => handleConversationEvent(event))
    .listenForWhisper('typing', e => showTypingIndicator(e));
```

### Typing indicator

```javascript
// Sender
channel.whisper('typing', { userId: authUser.id, name: authUser.name, isTyping: true });

// Receiver
channel.listenForWhisper('typing', e => {
    if (e.isTyping) {
        typingUser.value = e.name;
        setTimeout(() => { typingUser.value = null; }, 3000);
    }
});
```

---

## Frontend Integration — React

### Install dependencies

```bash
npm install laravel-echo pusher-js
```

### echo.js (Reverb)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;
window.axios  = axios;
axios.defaults.withCredentials = true;

const wsHost  = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
const port    = Number(import.meta.env.VITE_REVERB_PORT ?? 80);
const forceTLS = (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key:         import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort:      port,
    wssPort:     port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
});

export default window.Echo;
```

### React hook example

```javascript
import { useEffect, useRef } from 'react';
import Echo from '../echo';

export function useChat(conversationId, userId) {
    const channelRef = useRef(null);

    useEffect(() => {
        if (! conversationId) return;

        // Join conversation channel
        channelRef.current = Echo.join(`conversation.${conversationId}`)
            .listen('.MessageEvent', event => {
                if (event.type === 'sent')    { /* add message to state */ }
                if (event.type === 'seen')    { /* update status */        }
                if (event.type === 'reaction'){ /* update reactions */     }
            })
            .listen('.ConversationEvent', event => {
                if (event.action === 'member_added') { /* update members list */ }
            });

        // Join personal channel
        const userChannel = Echo.private(`user.${userId}`)
            .listen('.ConversationEvent', event => {
                if (event.action === 'added') { /* add conversation to list */ }
            });

        return () => {
            Echo.leave(`conversation.${conversationId}`);
            Echo.leave(`user.${userId}`);
        };
    }, [conversationId, userId]);

    const sendTyping = () => {
        channelRef.current?.whisper('typing', { userId, isTyping: true });
    };

    return { sendTyping };
}
```

---

## Uninstall

```bash
php artisan chat:uninstall
composer remove rahatulrabbi/laravel-chat
```

To keep database tables during uninstall:

```bash
php artisan chat:uninstall --keep-data
```

---

## Changelog

### v1.0.0
- Initial release
- Private and group conversations
- Real-time messaging via Laravel Reverb and Pusher
- Message reactions, replies, forwarding, pinning
- Message status: sent, delivered, seen
- File attachments with media library
- Group management: roles, invite links, mute, settings
- User blocking and restricting
- FCM push notifications via Firebase
- Auto-unmute scheduler
- Vue 3 and React integration guide

---

## License

MIT License. See [LICENSE](LICENSE) file.
