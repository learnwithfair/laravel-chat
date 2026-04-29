<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    | The fully qualified class name of your application's User model.
    |
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | User Field Mapping
    |--------------------------------------------------------------------------
    | Map your User model columns to the names the package expects internally.
    | Change the values to match your actual database column names.
    |
    | Example: if your column is "profile_photo" instead of "avatar_path",
    | set 'avatar' => 'profile_photo'
    |
    */
    'user_fields' => [
        'id'        => 'id',
        'name'      => 'name',
        'avatar'    => 'avatar_path',
        'last_seen' => 'last_seen_at',
        'is_active' => 'is_active',
    ],

    /*
    |--------------------------------------------------------------------------
    | Online Threshold
    |--------------------------------------------------------------------------
    | Number of minutes after the last seen timestamp before a user is
    | considered offline. Default: 2 minutes.
    |
    */
    'online_threshold_minutes' => env('CHAT_ONLINE_THRESHOLD', 2),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    | Control the package's built-in API routes.
    | Set 'enabled' to false if you want to define routes manually.
    |
    */
    'routing' => [
        'enabled'    => true,
        'prefix'     => env('CHAT_ROUTE_PREFIX', 'api/v1'),
        'middleware' => ['api', 'auth:sanctum', 'laravel-chat.last-seen'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Driver
    |--------------------------------------------------------------------------
    | Supported: "reverb", "pusher", "ably", "log", "null"
    |
    */
    'broadcasting' => [
        'driver' => env('BROADCAST_DRIVER', 'reverb'),

        'reverb' => [
            'app_id' => env('REVERB_APP_ID'),
            'key'    => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'host'   => env('REVERB_HOST', '127.0.0.1'),
            'port'   => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],

        'pusher' => [
            'app_id'  => env('PUSHER_APP_ID'),
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'use_tls' => env('PUSHER_SCHEME', 'https') === 'https',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Uploads
    |--------------------------------------------------------------------------
    | Configure storage for message attachments and group avatars.
    |
    */
    'uploads' => [
        'disk'              => env('CHAT_UPLOAD_DISK', 'public'),
        'message_path'      => 'uploads/messages',
        'group_avatar_path' => 'uploads/groups/avatars',
        'max_file_size_kb'  => env('CHAT_MAX_FILE_SIZE', 51200),
        'allowed_types'     => [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'video' => ['mp4', 'mov', 'avi', 'mkv', 'webm'],
            'audio' => ['mp3', 'wav', 'ogg'],
            'file'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notifications
    |--------------------------------------------------------------------------
    | Requires kreait/laravel-firebase. Place your Firebase service account
    | JSON at: storage/app/firebase/service-account.json
    |
    */
    'push_notifications' => [
        'enabled'  => env('CHAT_PUSH_NOTIFICATIONS', false),
        'provider' => 'fcm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invite URL
    |--------------------------------------------------------------------------
    | Base URL used when generating group invite links.
    |
    */
    'invite_url' => env('CHAT_INVITE_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'conversations' => 30,
        'messages'      => 20,
        'pinned'        => 40,
        'members'       => 50,
        'media'         => 30,
        'users'         => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Settings
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'unsent_placeholder' => 'Unsent',
        'allow_edit'         => true,
        'allow_forward'      => true,
        'allow_pin'          => true,
        'allow_reactions'    => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Group Defaults
    |--------------------------------------------------------------------------
    | Default group_settings values when a new group is created.
    |
    */
    'group_defaults' => [
        'allow_members_to_send_messages'           => true,
        'allow_members_to_add_remove_participants' => false,
        'allow_members_to_change_group_info'       => false,
        'admins_must_approve_new_members'          => false,
        'allow_invite_users_via_link'              => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('CHAT_CACHE_ENABLED', true),
        'ttl'     => env('CHAT_CACHE_TTL', 300),
        'prefix'  => 'laravel_chat',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('CHAT_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name'       => env('CHAT_QUEUE_NAME', 'chat'),
    ],

];
