packages/rahatulrabbi/talkbridge/
├── composer.json
├── LICENSE
├── README.md
├── CHANGELOG.md
├── phpunit.xml
├── .gitignore
│
├── config/
│   └── laravel-chat.php          ← all customization options
│
├── routes/
│   ├── api.php                   ← all API routes
│   └── channels.php              ← broadcast channel definitions
│
├── database/migrations/          ← 13 migrations (ordered by timestamp)
│
├── lang/en/
│   └── messages.php
│
├── stubs/
│   ├── channels.stub             ← publishable channel file
│   ├── echo-reverb.stub          ← frontend echo config (Reverb)
│   └── echo-pusher.stub          ← frontend echo config (Pusher)
│
├── src/
│   ├── LaravelChatServiceProvider.php
│   ├── Actions/Chat/             ← CreateConversation, SendMessage, MarkRead
│   ├── Commands/                 ← install, uninstall, publish, auto-unmute
│   ├── Events/                   ← ConversationEvent, MessageEvent
│   ├── Helpers/helpers.php       ← chat_upload_file, chat_delete_file, chat_get_file_type
│   ├── Http/
│   │   ├── Controllers/Api/V1/Chat/   ← 5 controllers
│   │   ├── Middleware/UpdateLastSeen.php
│   │   ├── Requests/Chat/        ← 5 form requests + BaseRequest
│   │   └── Resources/Chat/       ← 5 resources
│   ├── Jobs/                     ← SendPushNotification, UnmuteConversation
│   ├── Models/                   ← 10 Eloquent models
│   ├── Repositories/Chat/        ← ConversationRepository, MessageRepository
│   ├── Services/ChatService.php
│   └── Traits/ApiResponse.php
│
└── tests/
    ├── TestCase.php
    └── Feature/                  ← MessageTest, ConversationTest