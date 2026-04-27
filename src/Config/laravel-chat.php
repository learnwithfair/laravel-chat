<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    | The fully qualified class name of your User model. The package will use
    | this model for all user-related queries and relationships.
    |
    */
    'user_model'               => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | User Fields Mapping
    |--------------------------------------------------------------------------
    | Map your User model's fields to the package's expected field names.
    | This allows full flexibility regardless of your column naming convention.
    |
    | Example: if your users table has 'profile_photo' instead of 'avatar',
    | set 'avatar' => 'profile_photo'
    |
    */
    'user_fields'              => [
        'id'        => 'id',
        'name'      => 'name',
        'avatar'    => 'avatar_path',  // ← change to your avatar column name
        'last_seen' => 'last_seen_at', // ← change to your last_seen column name
        'is_active' => 'is_active',    // ← optional: filter inactive users
    ],

    /*
    |--------------------------------------------------------------------------
    | Online Presence
    |--------------------------------------------------------------------------
    | Define how long (in minutes) a user is considered "online" after their
    | last API activity. Default is 2 minutes.
    |
    */
    'online_threshold_minutes' => env('CHAT_ONLINE_THRESHOLD', 2),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    | Control the API routing behavior. You can disable built-in routes and
    | define your own, or customize prefix and middleware stack.
    |
    */
    'routing'                  => [
        'enabled'    => true,
        'prefix'     => env('CHAT_ROUTE_PREFIX', 'api/v1'),
        'middleware' => ['api', 'auth:sanctum', 'laravel-chat.last-seen'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting (Real-Time)
    |--------------------------------------------------------------------------
    | Configure which broadcasting driver to use.
    | Options: 'reverb' | 'pusher' | 'ably' | 'log' | 'null'
    |
    */
    'broadcasting'             => [
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
    | Configure file storage settings for message attachments and group avatars.
    |
    */
    'uploads'                  => [
        'disk'              => env('CHAT_UPLOAD_DISK', 'public'),
        'message_path'      => 'uploads/messages',
        'group_avatar_path' => 'uploads/groups/avatars',
        'max_file_size_kb'  => env('CHAT_MAX_FILE_SIZE', 51200), // 50MB
        'allowed_types'     => [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'video' => ['mp4', 'mov', 'avi', 'mkv', 'webm'],
            'audio' => ['mp3', 'wav', 'ogg'],
            'file'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notifications (FCM via Firebase)
    |--------------------------------------------------------------------------
    | Enable/disable push notifications. Requires kreait/laravel-firebase.
    | Set firebase credentials in config/firebase.php.
    |
    */
    'push_notifications'       => [
        'enabled'  => env('CHAT_PUSH_NOTIFICATIONS', false),
        'provider' => 'fcm', // Currently only 'fcm' supported
    ],

    /*
    |--------------------------------------------------------------------------
    | Invite Links
    |--------------------------------------------------------------------------
    | Base URL used for generating group invite links.
    |
    */
    'invite_url'               => env('CHAT_INVITE_URL', env('APP_URL') . '/api/v1/accept-invite'),

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    | Default per-page limits for paginated responses.
    |
    */
    'pagination'               => [
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
    | Fine-tune message behaviour.
    |
    */
    'messages'                 => [
        'unsent_placeholder' => 'Unsent',
        'allow_edit'         => true,
        'allow_forward'      => true,
        'allow_pin'          => true,
        'allow_reactions'    => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Group Settings Defaults
    |--------------------------------------------------------------------------
    | Default settings applied when a new group is created.
    |
    */
    'group_defaults'           => [
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
    | Enable/disable caching for expensive queries.
    |
    */
    'cache'                    => [
        'enabled' => env('CHAT_CACHE_ENABLED', true),
        'ttl'     => env('CHAT_CACHE_TTL', 300), // seconds
        'prefix'  => 'laravel_chat',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Queue settings for async jobs like push notifications and unmute tasks.
    |
    */
    'queue'                    => [
        'connection' => env('CHAT_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name'       => env('CHAT_QUEUE_NAME', 'chat'),
    ],

];
