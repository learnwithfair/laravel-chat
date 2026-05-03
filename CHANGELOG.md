# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.2] - 2024-01-03

### Fixed
- `installReverb()`: `reverb:install` command does not exist until `package:discover`
  runs after the Composer install. Fixed by:
  1. Running `package:discover` after `composer require laravel/reverb`.
  2. Checking `Artisan::all()` for `reverb:install` before calling it.
  3. Fallback: writing the Reverb connection block directly into
     `config/broadcasting.php` if `reverb:install` is still unavailable.

### Added
- `php artisan talkbridge:version` — shows installed version and recent changelog.
  Use `--check` flag to compare with latest version on Packagist.
- `php artisan talkbridge:update` — runs `composer update`, re-publishes migrations,
  runs new migrations, verifies User model patch, and clears caches.
  Use `--force` to also overwrite config and stubs.

---

## [1.0.1] - 2024-01-02

### Fixed
- ServiceProvider: wrapped `registerRoutes()`, `registerBroadcastChannels()`, and
  `registerScheduler()` inside `$this->app->booted()` to prevent silent fatal errors
  when Broadcast / Route facades are not fully ready during `boot()`.
- `channels.php`: moved `config()` calls inside channel closures instead of file scope,
  preventing errors during require at boot time.
- `ComposerRunner::run()` made public so `InstallCommand::dumpAutoload()` can call it directly.
- `InstallCommand`: removed default values from `--broadcaster` and `--push` options so
  the interactive choice prompt always fires unless the flag is explicitly passed.
- `InstallCommand`: broadcaster and push provider choice now correctly extracts the key
  from the descriptive choice string.
- `InstallCommand`: runs `composer dump-autoload --optimize` after each package install
  so new classes are immediately available without restarting.
- `InstallCommand`: saves `TALKBRIDGE_INSTALLED_BROADCASTER` and `TALKBRIDGE_INSTALLED_PUSH`
  to `.env` so `UninstallCommand` knows exactly which packages to remove.
- `UninstallCommand`: reads `TALKBRIDGE_INSTALLED_BROADCASTER` and `TALKBRIDGE_INSTALLED_PUSH`
  from `.env` to only remove packages that TalkBridge actually installed.
- `UninstallCommand`: broadcaster-specific env keys (REVERB_*, PUSHER_*, ABLY_KEY) are now
  removed based on which driver was installed, not a hardcoded full list.

### Added
- `ComposerRunner::dumpAutoload()` helper method.
- README: comprehensive customization guide — override controllers, extend ChatService,
  extend resources, listen to events, use helpers and trait methods directly.
- README: full API endpoint reference table with request body notes.
- README: composite name column documentation.

---

## [1.0.0] - 2024-01-01

### Added
- Initial release as `rahatulrabbi/talkbridge`
- Fully automatic install and uninstall (zero manual steps)
- Auto-installs broadcaster package (Reverb / Pusher / Ably) on install
- Auto-installs push notification package (FCM / Web Push / both) on install
- Dynamic user name: single column or composite (`first_name` + `last_name` etc.)
- Detects `$fillable` vs `$guarded` and patches User model accordingly
- `HasTalkBridgeFeatures` trait with marker-based injection and removal
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
- Browser Web Push via minishlink/web-push (VAPID)
- Middleware, scheduler, channels, routes — all auto-registered
- Vue 3, React, Flutter, React Native integration guides
