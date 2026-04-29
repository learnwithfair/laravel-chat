# Changelog

All notable changes to this package are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-05-01

### Added
- Initial release
- Private and group conversations
- Real-time broadcasting via Laravel Reverb and Pusher
- Message reactions, replies, forwarding, and pinning
- Message status tracking: sent, delivered, seen
- File attachments with media library (images, video, audio, files, links)
- Group management: super_admin / admin / member roles
- Group invite links with expiry and usage limits
- Mute / unmute conversations (timed or indefinite)
- Auto-unmute via scheduler and queue
- User blocking and restricting
- FCM push notifications via kreait/laravel-firebase
- Configurable user field mapping (avatar, last_seen column names)
- Configurable routing prefix and middleware
- Interactive install wizard: `chat:install`
- Uninstall command: `chat:uninstall`
- Selective publish command: `chat:publish`
- Vue 3 and React frontend integration guide
