<?php
use Illuminate\Support\Facades\Broadcast;
use RahatulRabbi\TalkBridge\Models\ConversationParticipant;

$avatarField = config('talkbridge.user_fields.avatar', 'avatar_path');

Broadcast::channel('online', function ($user) use ($avatarField) {
    return [
        'id'     => $user->id,
        'name'   => talkbridge_user_name($user),
        'avatar' => $user->{$avatarField} ?? null,
    ];
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) use ($avatarField) {
    $isMember = ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->where('is_active', true)
        ->exists();

    if (! $isMember) return false;

    return [
        'id'     => $user->id,
        'name'   => talkbridge_user_name($user),
        'avatar' => $user->{$avatarField} ?? null,
    ];
});
