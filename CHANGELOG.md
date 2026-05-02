# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.0] - 2024-01-01

### Added
- Initial release as `rahatulrabbi/talkbridge`
- Fully automatic install and uninstall (zero manual steps)
- Auto-installs broadcaster package (Reverb / Pusher / Ably) on install
- Auto-installs push notification package (FCM / Web Push / both) on install
- Auto-removes optional packages on uninstall
- Dynamic user name support: single column or composite (first_name + last_name etc.)
- Detects $fillable vs $guarded and patches User model accordingly
- Private and group conversations
- Real-time broadcasting via Reverb and Pusher
- Message reactions, replies, forwarding, pinning
- Message status: sent, delivered, seen
- File attachments with media library
- Group roles: super_admin / admin / member
- Group invite links with expiry and usage limits
- Mute / unmute (timed or indefinite) with auto-unmute scheduler
- User blocking and restricting
- FCM mobile push notifications via kreait/laravel-firebase
- Browser Web Push via minishlink/web-push (VAPID)
- Middleware, scheduler, channels, routes — all auto-registered
- Vue 3, React, Flutter, React Native integration guides
