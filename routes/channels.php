<?php

use Illuminate\Support\Facades\Broadcast;
use RahatulRabbi\TalkBridge\Models\Conversation;
use RahatulRabbi\TalkBridge\Models\ConversationParticipant;

$avatarField = config('laravel-chat.user_fields.avatar', 'avatar_path');

// Global online presence channel
Broadcast::channel('online', function ($user) use ($avatarField) {
    return [
        'id'     => $user->id,
        'name'   => $user->name,
        'avatar' => $user->{$avatarField} ?? null,
    ];
});

// Per-user private channel (personal notifications)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Conversation presence channel (messages, typing, online status)
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) use ($avatarField) {
    $isMember = ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->where('is_active', true)
        ->exists();

    if (! $isMember) {
        return false;
    }

    return [
        'id'     => $user->id,
        'name'   => $user->name,
        'avatar' => $user->{$avatarField} ?? null,
    ];
});
