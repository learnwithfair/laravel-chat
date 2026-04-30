<?php

namespace RahatulRabbi\TalkBridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function groupSetting(): HasOne
    {
        return $this->hasOne(GroupSettings::class);
    }

    public function unreadMessages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(ConversationInvite::class);
    }

    public function activeInvites(): HasMany
    {
        return $this->hasMany(ConversationInvite::class)->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('laravel-chat.user_model'), 'created_by');
    }

    public function getInviteLinkAttribute()
    {
        return $this->activeInvites->sortByDesc('created_at')->first();
    }

    public function otherParticipant(Model $currentUser): ?Model
    {
        return $this->participants->where('user_id', '!=', $currentUser->id)->first()?->user;
    }

    public function canUserSendMessage(?ConversationParticipant $participant = null): bool
    {
        if ($this->type === 'private') {
            return true;
        }

        if ($participant && in_array($participant->role, ['admin', 'super_admin'])) {
            return true;
        }

        $settings = $this->groupSetting;

        if (! $settings) {
            return true;
        }

        return (bool) $settings->allow_members_to_send_messages;
    }
}
