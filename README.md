# Laravel Chat

A professional, real-time chat package for Laravel 11 and 12.

Supports private and group conversations, message reactions, file attachments, message status tracking (sent / delivered / seen), typing indicators, user blocking, group management, FCM push notifications, and WebSocket broadcasting via Laravel Reverb or Pusher.

**Everything is automatic — install and uninstall touch zero files manually.**

---

## Requirements

| Dependency              | Version       |
|-------------------------|---------------|
| PHP                     | 8.2 or higher |
| Laravel                 | 11.x or 12.x  |
| Laravel Sanctum         | 4.x           |
| Laravel Reverb          | 1.x (optional — Reverb driver) |
| kreait/laravel-firebase | 5.x (optional — FCM push notifications) |

---

## Installation

### Step 1 — Install via Composer

```bash
composer require rahatulrabbi/laravel-chat
```

### Step 2 — Run the wizard

```bash
php artisan chat:install
```

That is all. The wizard does everything automatically:

| What                              | How                                      |
|-----------------------------------|------------------------------------------|
| Config file                       | Published to `config/laravel-chat.php`   |
| Database migrations               | Published to `database/migrations/`      |
| .env variables                    | Written automatically                    |
| `HasChatFeatures` trait           | Injected into `App\Models\User`          |
| Middleware alias `last-seen`      | Auto-registered by ServiceProvider       |
| Scheduler `chat:auto-unmute`      | Auto-registered by ServiceProvider       |
| Broadcast channels                | Auto-registered by ServiceProvider       |
| API routes under `api/v1`         | Auto-registered by ServiceProvider       |

No edits to `bootstrap/app.php`, `routes/channels.php`, or `App\Models\User` are needed.

### Step 3 — Start the WebSocket server

```bash
php artisan reverb:start --debug
```

### Step 4 — Start the queue worker

```bash
php artisan queue:work --queue=chat
```

---

## What gets injected into your User model

During install, the following trait is injected automatically into `App\Models\User`:

```php
// @laravel-chat:use-start
use \RahatulRabbi\LaravelChat\Traits\HasChatFeatures;
// @laravel-chat:use-end
```

The `HasChatFeatures` trait provides:

- `blockedUsers()` / `blockedByUsers()` / `hasBlocked()` / `isBlockedBy()`
- `restrictedUsers()` / `restrictedByUsers()` / `hasRestricted()`
- `deviceTokens()` — for FCM push notifications
- `isOnline()` — checks `last_seen_at` against the configured threshold

On uninstall, the markers are used to locate and remove these lines cleanly.

---

## Configuration

After publishing, open `config/laravel-chat.php`. The most important section is `user_fields`:

```php
'user_fields' => [
    'id'        => 'id',
    'name'      => 'name',
    'avatar'    => 'avatar_path',     // change to your column name
    'last_seen' => 'last_seen_at',    // change to your column name
],
```

All other options have sensible defaults. Full reference:

| Key                          | Default         | Description                                |
|------------------------------|-----------------|--------------------------------------------|
| `user_model`                 | `App\Models\User` | Your User model class                    |
| `online_threshold_minutes`   | `2`             | Minutes before user is considered offline  |
| `routing.enabled`            | `true`          | Disable to define routes manually          |
| `routing.prefix`             | `api/v1`        | URL prefix for all chat routes             |
| `routing.middleware`         | see config      | Middleware stack for chat routes           |
| `broadcasting.driver`        | `reverb`        | `reverb` or `pusher`                       |
| `uploads.disk`               | `public`        | Laravel storage disk for file uploads      |
| `uploads.max_file_size_kb`   | `51200`         | 50 MB limit                                |
| `push_notifications.enabled` | `false`         | Enable FCM notifications                   |
| `invite_url`                 | APP_URL based   | Base URL for group invite links            |
| `queue.name`                 | `chat`          | Queue name for async jobs                  |

---

## Artisan Commands

| Command                              | Description                                      |
|--------------------------------------|--------------------------------------------------|
| `php artisan chat:install`           | Install wizard — fully automatic                 |
| `php artisan chat:uninstall`         | Remove everything — fully automatic              |
| `php artisan chat:uninstall --keep-data` | Uninstall but keep database tables           |
| `php artisan chat:publish --tag=config` | Re-publish only the config                   |
| `php artisan chat:publish --tag=migrations` | Re-publish only migrations               |
| `php artisan chat:publish --tag=stubs` | Publish stubs for customization              |
| `php artisan chat:auto-unmute`       | Process expired mutes (auto-run by scheduler)    |

---

## API Endpoints

All endpoints are prefixed with `/api/v1` and require Sanctum authentication.

### Conversations

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/conversations` | List all conversations |
| POST   | `/conversations` | Create group |
| POST   | `/conversations/private` | Start or get private conversation |
| DELETE | `/conversations/{id}` | Remove conversation for self only |
| GET    | `/conversations/{id}/media` | Media library (images, video, audio, files, links) |

### Messages

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/messages` | Send message |
| GET    | `/messages/{conversation}` | Get paginated messages |
| PUT    | `/messages/{message}` | Edit message |
| DELETE | `/messages/delete-for-me` | Delete for current user only |
| DELETE | `/messages/delete-for-everyone` | Unsend for all participants |
| GET    | `/messages/seen/{conversation}` | Mark all messages seen (on open) |
| POST   | `/messages/mark-seen` | Mark specific message IDs seen |
| GET    | `/messages/delivered/{conversation}` | Mark as delivered |
| POST   | `/messages/{message}/forward` | Forward to other conversations |
| POST   | `/messages/{message}/toggle-pin` | Pin or unpin |
| GET    | `/messages/{conversation}/pinned-messages` | Get pinned messages |

### Reactions

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/messages/{message}/reaction` | Toggle reaction (body: `{"reaction":"❤️"}`) |
| GET    | `/messages/{message}/reaction` | List all reactions grouped by type |

### Group Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/group/{id}/update` | Update name, description, avatar, settings |
| POST   | `/group/{id}/members/add` | Add members |
| POST   | `/group/{id}/members/remove` | Remove members |
| GET    | `/group/{id}/members` | List all members |
| POST   | `/group/{id}/admins/add` | Promote to admin |
| POST   | `/group/{id}/admins/remove` | Demote to member |
| POST   | `/group/{id}/mute` | Mute (body: `{"minutes": 60}` / `-1` = forever / `0` = unmute) |
| POST   | `/group/{id}/leave` | Leave group |
| DELETE | `/group/{id}/delete-group` | Delete group (super_admin only) |
| POST   | `/group/{id}/regenerate-invite` | Regenerate invite link |
| GET    | `/accept-invite/{token}` | Join via invite link |

### Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/available-users?search=name` | Search users to start conversation |
| GET    | `/online-users` | Currently online users |
| POST   | `/users/{user}/block-toggle` | Block or unblock |
| POST   | `/users/{user}/restrict-toggle` | Restrict or unrestrict |

---

## Real-Time Events

### ConversationEvent

Broadcast to `user.{userId}` (private channel) when `targetUserId` is set, otherwise to `conversation.{id}` (presence channel).

| Action | Trigger |
|--------|---------|
| `added` | User added to or created a conversation |
| `removed` | User removed from group |
| `left` | User left group |
| `updated` | Group name / avatar / settings changed |
| `deleted` | Group deleted |
| `blocked` | User blocked |
| `unblocked` | User unblocked |
| `unmuted` | Conversation unmuted by scheduler |
| `member_added` | New member joined |
| `member_left` | Member left |
| `admin_added` | Member promoted to admin |
| `admin_removed` | Admin demoted to member |

### MessageEvent

Always broadcast to `conversation.{id}` (presence channel).

| Type | Trigger |
|------|---------|
| `sent` | New message sent |
| `updated` | Message edited |
| `deleted_for_everyone` | Message unsent |
| `deleted_permanent` | Already-unsent message hard deleted |
| `reaction` | Reaction added or removed |
| `delivered` | Message delivered to recipient |
| `seen` | Message seen by recipient |
| `pinned` | Message pinned |
| `unpinned` | Message unpinned |

---

## Frontend Integration — Vue 3

### Install dependencies

```bash
npm install laravel-echo pusher-js
```

### resources/js/echo.js (Reverb)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;
window.axios  = axios;
axios.defaults.withCredentials = true;

const wsHost   = import.meta.env.VITE_REVERB_HOST   ?? window.location.hostname;
const port     = Number(import.meta.env.VITE_REVERB_PORT ?? 80);
const forceTLS = (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https';

window.Echo = new Echo({
    broadcaster:       'reverb',
    key:               import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort:            port,
    wssPort:           port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
});
```

Import once in `resources/js/app.js`:

```javascript
import './echo';
```

### resources/js/echo.js (Pusher)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;
window.axios  = axios;
axios.defaults.withCredentials = true;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key:         import.meta.env.VITE_PUSHER_APP_KEY,
    cluster:     import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS:    true,
});
```

### Channel subscriptions

```javascript
// Global online presence
window.Echo.join('online')
    .here(users  => { onlineUsers.value = users; })
    .joining(user => { onlineUsers.value.push(user); })
    .leaving(user => { onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id); });

// Personal notifications (conversation added/removed/blocked etc.)
window.Echo.private(`user.${authUser.id}`)
    .listen('.ConversationEvent', event => handleConversationEvent(event));

// Conversation channel (messages + presence)
const channel = window.Echo.join(`conversation.${conversationId}`)
    .listen('.MessageEvent',      event => handleMessageEvent(event))
    .listen('.ConversationEvent', event => handleConversationEvent(event))
    .listenForWhisper('typing',   data  => handleTyping(data))
    .here(users  => { activeUsers.value = users; })
    .joining(user => { activeUsers.value.push(user); })
    .leaving(user => { activeUsers.value = activeUsers.value.filter(u => u.id !== user.id); });
```

### Typing indicator

```javascript
// Sender side
channel.whisper('typing', { userId: authUser.id, name: authUser.name, isTyping: true });

// Receiver side
channel.listenForWhisper('typing', ({ name, isTyping }) => {
    if (isTyping) {
        typingUser.value = name;
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => { typingUser.value = null; }, 3000);
    }
});
```

---

## Frontend Integration — React

### Install dependencies

```bash
npm install laravel-echo pusher-js
```

### src/echo.js (Reverb)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;
window.axios  = axios;
axios.defaults.withCredentials = true;

const wsHost   = import.meta.env.VITE_REVERB_HOST   ?? window.location.hostname;
const port     = Number(import.meta.env.VITE_REVERB_PORT ?? 80);
const forceTLS = (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https';

const echo = new Echo({
    broadcaster:       'reverb',
    key:               import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort:            port,
    wssPort:           port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
});

export default echo;
```

### useChat hook

```javascript
import { useEffect, useRef, useState } from 'react';
import echo from '../echo';

export function useChat(conversationId, userId) {
    const channelRef       = useRef(null);
    const [messages, setMessages]   = useState([]);
    const [typingUser, setTypingUser] = useState(null);
    const typingTimer = useRef(null);

    useEffect(() => {
        if (! conversationId) return;

        channelRef.current = echo
            .join(`conversation.${conversationId}`)
            .listen('.MessageEvent', ({ type, payload }) => {
                if (type === 'sent')               setMessages(prev => [payload, ...prev]);
                if (type === 'deleted_for_everyone') setMessages(prev => prev.map(m => m.id === payload.id ? payload : m));
                if (type === 'seen')               { /* update statuses */ }
                if (type === 'reaction')           { /* update reactions */ }
            })
            .listen('.ConversationEvent', ({ action }) => {
                if (action === 'member_added') { /* refresh members */ }
            })
            .listenForWhisper('typing', ({ name, isTyping }) => {
                if (isTyping) {
                    setTypingUser(name);
                    clearTimeout(typingTimer.current);
                    typingTimer.current = setTimeout(() => setTypingUser(null), 3000);
                }
            });

        const userChannel = echo
            .private(`user.${userId}`)
            .listen('.ConversationEvent', ({ action }) => {
                if (action === 'added') { /* add conversation to sidebar list */ }
            });

        return () => {
            echo.leave(`conversation.${conversationId}`);
            echo.leave(`user.${userId}`);
        };
    }, [conversationId, userId]);

    const sendTyping = () => {
        channelRef.current?.whisper('typing', { userId, isTyping: true });
    };

    return { messages, typingUser, sendTyping };
}
```

---

## Uninstall

```bash
php artisan chat:uninstall
```

This removes automatically:

- `HasChatFeatures` trait from `App\Models\User`
- All chat database tables
- `config/laravel-chat.php`
- Published migration files
- Published stubs
- All `CHAT_*` and broadcaster `.env` variables

To keep the database tables:

```bash
php artisan chat:uninstall --keep-data
```

To remove the Composer package after uninstalling:

```bash
composer remove rahatulrabbi/laravel-chat
```

---

## Changelog

### v1.0.0

- Initial release
- Zero-touch install and uninstall
- Private and group conversations
- Real-time broadcasting via Reverb and Pusher
- Message reactions, replies, forwarding, pinning
- Message status: sent, delivered, seen
- File attachments with media library
- Group roles: super_admin / admin / member
- Group invite links with expiry and usage limits
- Mute / unmute (timed or indefinite) with auto-unmute scheduler
- User blocking and restricting
- FCM push notifications via kreait/laravel-firebase
- Vue 3 and React integration guide

---

## License

MIT License.
