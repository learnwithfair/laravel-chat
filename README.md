# TalkBridge

**Real-time chat package for Laravel 11, 12 and 13.**

Private and group conversations, message reactions, file attachments, message status tracking (sent / delivered / seen), typing indicators, user blocking, group management, FCM and Web Push notifications, WebSocket broadcasting via Reverb or Pusher.

**Zero manual steps. Install once, everything is configured automatically.**

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.2 or higher |
| Laravel | 11.x or 12.x or 13.x |
| Laravel Sanctum | 4.x |

All optional packages (Reverb, Pusher, Firebase, Web Push) are installed automatically based on your choices during `talkbridge:install`.

---

## Installation

### Step 1 — Install via Composer

```bash
composer require rahatulrabbi/talkbridge
```

### Step 2 — Run the wizard

```bash
php artisan talkbridge:install
```

The wizard prompts you for:

| Prompt | Options |
|---|---|
| Broadcasting driver | `reverb` `pusher` `ably` `log` `null` |
| Push notification provider | `none` `fcm` `web` `both` |
| Run migrations now? | yes / no |

Based on your answers it automatically:

| Action | How |
|---|---|
| Installs broadcaster package | `composer require laravel/reverb` or `pusher/pusher-php-server` |
| Installs push notification package | `composer require kreait/laravel-firebase` and/or `minishlink/web-push` |
| Publishes `config/talkbridge.php` | `vendor:publish` |
| Publishes migrations | `vendor:publish` |
| Writes all `.env` variables | File::append |
| Injects `HasTalkBridgeFeatures` into User model | Marker-based AST injection |
| Adds `last_seen_at` to `$fillable` (if model uses fillable) | Regex patch |
| Registers middleware alias `talkbridge.last-seen` | ServiceProvider |
| Registers scheduler `talkbridge:auto-unmute` every minute | ServiceProvider |
| Registers broadcast channels | ServiceProvider |
| Registers API routes under `api/v1` | ServiceProvider |
| Runs migrations | Artisan::call |

---

## What gets injected into your User model

```php
// @talkbridge:start
use \RahatulRabbi\TalkBridge\Traits\HasTalkBridgeFeatures;
// @talkbridge:end
```

The `HasTalkBridgeFeatures` trait provides:

- `getChatDisplayName()` — dynamic name, supports composite columns
- `getChatAvatar()` — avatar from configured column
- `getChatLastSeen()` — human diff of last_seen_at
- `isOnline()` — checks against configured threshold
- `blockedUsers()` / `blockedByUsers()` / `hasBlocked()` / `isBlockedBy()`
- `restrictedUsers()` / `restrictedByUsers()` / `hasRestricted()`
- `deviceTokens()` — for push notifications

---

## Configuration

Open `config/talkbridge.php`. The most important section:

```php
'user_fields' => [
    'id'        => 'id',
    'name'      => 'name',             // single column
    // 'name'   => ['first_name', 'last_name'],   // composite name
    // 'name'   => ['f_name', 'm_name', 'l_name'], // three parts
    'avatar'    => 'avatar_path',      // your avatar column
    'last_seen' => 'last_seen_at',     // your last_seen column
    'is_active' => null,               // set to 'is_active' if you have it
],
```

Full config reference:

| Key | Default | Description |
|---|---|---|
| `user_model` | `App\Models\User` | Your User model |
| `user_fields.name` | `'name'` | Single column or array for composite |
| `user_fields.avatar` | `'avatar_path'` | Avatar column name |
| `user_fields.last_seen` | `'last_seen_at'` | Last seen column name |
| `online_threshold_minutes` | `2` | Minutes before user goes offline |
| `routing.enabled` | `true` | Disable to define routes manually |
| `routing.prefix` | `api/v1` | URL prefix |
| `broadcasting.driver` | `reverb` | `reverb` or `pusher` |
| `push_notifications.provider` | `none` | `none` `fcm` `web` `both` |
| `uploads.disk` | `public` | Storage disk |
| `uploads.max_file_size_kb` | `51200` | 50 MB |
| `queue.name` | `talkbridge` | Queue name for async jobs |

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan talkbridge:install` | Install wizard — fully automatic |
| `php artisan talkbridge:uninstall` | Remove everything — fully automatic |
| `php artisan talkbridge:uninstall --keep-data` | Uninstall but keep tables |
| `php artisan talkbridge:uninstall --keep-packages` | Uninstall but keep optional packages |
| `php artisan talkbridge:publish --tag=config` | Re-publish config |
| `php artisan talkbridge:publish --tag=migrations` | Re-publish migrations |
| `php artisan talkbridge:publish --tag=stubs` | Publish customization stubs |
| `php artisan talkbridge:auto-unmute` | Process expired mutes (auto-run by scheduler) |

---

## API Endpoints

All under `/api/v1` with Sanctum auth.

### Conversations
| Method | Endpoint | Description |
|---|---|---|
| GET | `/conversations` | List all |
| POST | `/conversations` | Create group |
| POST | `/conversations/private` | Start private |
| DELETE | `/conversations/{id}` | Remove for self |
| GET | `/conversations/{id}/media` | Media library |

### Messages
| Method | Endpoint | Description |
|---|---|---|
| POST | `/messages` | Send |
| GET | `/messages/{conversation}` | Get paginated |
| PUT | `/messages/{message}` | Edit |
| DELETE | `/messages/delete-for-me` | Delete for self |
| DELETE | `/messages/delete-for-everyone` | Unsend |
| GET | `/messages/seen/{conversation}` | Mark all seen |
| POST | `/messages/mark-seen` | Mark specific seen |
| GET | `/messages/delivered/{conversation}` | Mark delivered |
| POST | `/messages/{message}/forward` | Forward |
| POST | `/messages/{message}/toggle-pin` | Pin / unpin |
| GET | `/messages/{conversation}/pinned-messages` | Get pinned |

### Reactions
| Method | Endpoint | Description |
|---|---|---|
| POST | `/messages/{message}/reaction` | Toggle `{"reaction":"❤️"}` |
| GET | `/messages/{message}/reaction` | List all reactions |

### Group Management
| Method | Endpoint | Description |
|---|---|---|
| POST | `/group/{id}/update` | Update name, avatar, settings |
| POST | `/group/{id}/members/add` | Add members |
| POST | `/group/{id}/members/remove` | Remove members |
| GET | `/group/{id}/members` | List members |
| POST | `/group/{id}/admins/add` | Promote to admin |
| POST | `/group/{id}/admins/remove` | Demote |
| POST | `/group/{id}/mute` | Mute `{"minutes":60}` / `-1`=forever / `0`=unmute |
| POST | `/group/{id}/leave` | Leave |
| DELETE | `/group/{id}/delete-group` | Delete (super_admin only) |
| POST | `/group/{id}/regenerate-invite` | New invite link |
| GET | `/accept-invite/{token}` | Join via link |

### Users
| Method | Endpoint | Description |
|---|---|---|
| GET | `/available-users?search=name` | Search users |
| GET | `/online-users` | Online users |
| POST | `/users/{user}/block-toggle` | Block / unblock |
| POST | `/users/{user}/restrict-toggle` | Restrict / unrestrict |

---

## Real-Time Events

### ConversationEvent — channel: `user.{id}` or `conversation.{id}`

| Action | When |
|---|---|
| `added` | Added to conversation |
| `removed` | Removed from group |
| `left` | Left group |
| `updated` | Group info changed |
| `deleted` | Group deleted |
| `blocked` / `unblocked` | Block toggled |
| `unmuted` | Auto-unmuted by scheduler |
| `member_added` / `member_left` | Membership change |
| `admin_added` / `admin_removed` | Role change |

### MessageEvent — channel: `conversation.{id}`

| Type | When |
|---|---|
| `sent` | New message |
| `updated` | Edited |
| `deleted_for_everyone` | Unsent |
| `deleted_permanent` | Hard deleted |
| `reaction` | Reaction toggled |
| `delivered` / `seen` | Status update |
| `pinned` / `unpinned` | Pin toggled |

---

## Frontend Integration

### Vue 3 / React (Web)

Install:
```bash
npm install laravel-echo pusher-js
```

Echo setup (Reverb) — copy from `stubs/talkbridge/echo-reverb.stub`:
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;
window.axios  = axios;
axios.defaults.withCredentials = true;

window.Echo = new Echo({
    broadcaster:       'reverb',
    key:               import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:            import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort:            Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort:           Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS:          (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

Channel subscriptions:
```javascript
// Global online presence
window.Echo.join('online')
    .here(users  => { onlineUsers.value = users; })
    .joining(user => { onlineUsers.value.push(user); })
    .leaving(user => { onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id); });

// Personal channel
window.Echo.private(`user.${authUser.id}`)
    .listen('.ConversationEvent', event => handleConversationEvent(event));

// Conversation channel
const channel = window.Echo.join(`conversation.${conversationId}`)
    .listen('.MessageEvent',      event => handleMessageEvent(event))
    .listen('.ConversationEvent', event => handleConversationEvent(event))
    .listenForWhisper('typing',   data  => handleTyping(data));

// Typing indicator — sender
channel.whisper('typing', { userId: authUser.id, name: authUser.name, isTyping: true });
```

### Flutter & React Native

See `docs/mobile/README.md`, `docs/mobile/flutter_integration.dart`, and `docs/mobile/react_native_integration.ts` for complete integration files.

---

## Uninstall

```bash
php artisan talkbridge:uninstall
```

Removes automatically:
- `HasTalkBridgeFeatures` from `App\Models\User` (and `last_seen_at` from `$fillable`)
- All chat database tables
- `config/talkbridge.php`, published migrations, stubs
- All `TALKBRIDGE_*` and broadcaster `.env` variables
- Optional packages (Reverb, Pusher, Firebase, Web Push) — with confirmation per package

```bash
composer remove rahatulrabbi/talkbridge
```

---

## License

MIT — see [LICENSE](LICENSE).

**Author:** MD. RAHATUL RABBI — [github.com/learnwithfair](https://github.com/learnwithfair)
