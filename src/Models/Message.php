<?php

namespace RahatulRabbi\TalkBridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
        'deleted_at'              => 'datetime',
        'edited_at'               => 'datetime',
        'is_deleted_for_everyone' => 'boolean',
        'is_restricted'           => 'boolean',
        'is_pinned'               => 'boolean',
    ];

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(config('laravel-chat.user_model'), 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(config('laravel-chat.user_model'), 'receiver_id');
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function statuses()
    {
        return $this->hasMany(MessageStatus::class);
    }

    public function deletions()
    {
        return $this->hasMany(MessageDeletion::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_message_id');
    }

    public function forwardedFrom()
    {
        return $this->belongsTo(Message::class, 'forward_to_message_id');
    }

    public function forwards()
    {
        return $this->hasMany(Message::class, 'forward_to_message_id');
    }
}
