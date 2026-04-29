<?php

namespace RahatulRabbi\LaravelChat\Repositories\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use RahatulRabbi\LaravelChat\Events\ConversationEvent;
use RahatulRabbi\LaravelChat\Events\MessageEvent;
use RahatulRabbi\LaravelChat\Http\Resources\Chat\ConversationResource;
use RahatulRabbi\LaravelChat\Models\Conversation;
use RahatulRabbi\LaravelChat\Models\ConversationInvite;
use RahatulRabbi\LaravelChat\Models\ConversationParticipant;
use RahatulRabbi\LaravelChat\Models\GroupSettings;
use RahatulRabbi\LaravelChat\Models\Message;
use RahatulRabbi\LaravelChat\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class ConversationRepository
{
    use ApiResponse;

    public function find(int $conversationId): ?Conversation
    {
        return Conversation::with(['participants.user', 'messages.sender'])->find($conversationId);
    }

    public function findUser(int $userId): Model
    {
        $userModel = config('laravel-chat.user_model');
        return $userModel::findOrFail($userId);
    }

    public function listFor(Model $user, int $perPage = 20, ?string $query = null)
    {
        $conversations = Conversation::query()
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('is_active', true);
            })
            ->when($query, function ($q) use ($query, $user) {
                $q->where(function ($q2) use ($query, $user) {
                    $q2->where(function ($group) use ($query) {
                        $group->where('type', 'group')->where('name', 'like', "%{$query}%");
                    })->orWhere(function ($private) use ($query, $user) {
                        $private->where('type', 'private')
                            ->whereHas('participants.user', function ($u) use ($query, $user) {
                                $u->where('users.id', '!=', $user->id)
                                  ->where('users.name', 'like', "%{$query}%");
                            });
                    });
                });
            })
            ->with([
                'participants' => function ($q) {
                    $q->where(function ($q) {
                        $q->whereNotNull('deleted_at')
                          ->orWhere(function ($q) {
                              $q->whereNull('deleted_at')->where('is_active', true);
                          });
                    })->with('user');
                },
                'lastMessage.sender',
                'lastMessage.attachments',
                'groupSetting',
                'creator:id,name',
                'activeInvites',
            ])
            ->withCount([
                'unreadMessages as unread_count' => function ($q) use ($user) {
                    $q->where('sender_id', '!=', $user->id)
                        ->whereColumn('messages.id', '>', 'conversation_participants.last_read_message_id')
                        ->join('conversation_participants', function ($join) use ($user) {
                            $join->on('conversation_participants.conversation_id', '=', 'messages.conversation_id')
                                 ->where('conversation_participants.user_id', $user->id);
                        });
                },
            ])
            ->latest('updated_at')
            ->paginate($perPage);

        return ConversationResource::collection($conversations);
    }

    public function findPrivateBetween(int $userId1, int $userId2): ?Conversation
    {
        return Conversation::where('type', 'private')
            ->whereHas('participants', fn($q) => $q->where('user_id', $userId1))
            ->whereHas('participants', fn($q) => $q->where('user_id', $userId2))
            ->first();
    }

    public function createPrivateConversation(int $userId1, int $userId2): ConversationResource
    {
        $conversation = Conversation::create(['type' => 'private']);

        $conversation->participants()->createMany([
            ['user_id' => $userId1, 'role' => 'member'],
            ['user_id' => $userId2, 'role' => 'member'],
        ]);

        $conversation->load([
            'participants' => fn($q) => $q->where('is_active', true)->with('user'),
            'lastMessage.sender',
            'groupSetting',
        ]);

        $conversation->setRelation('unread_count', 0);

        return new ConversationResource($conversation);
    }

    public function createGroupConversation(array $data, int $createdBy): ConversationResource
    {
        $creator      = $this->findUser($createdBy);
        $conversation = Conversation::create([
            'type'       => 'group',
            'name'       => $data['name'] ?? 'New Group',
            'created_by' => $createdBy,
        ]);

        $participants = [['user_id' => $createdBy, 'role' => 'super_admin', 'is_active' => true]];

        foreach ($data['participants'] ?? [] as $userId) {
            if ($userId == $createdBy) continue;
            $participants[] = ['user_id' => $userId, 'role' => 'member', 'is_active' => true];
        }

        $conversation->participants()->createMany($participants);

        $defaults = config('laravel-chat.group_defaults', []);
        $conversation->groupSetting()->create(array_merge($defaults, [
            'description' => $data['group']['description'] ?? null,
            'type'        => $data['group']['type'] ?? 'private',
        ]));

        $this->createInviteLink($creator, $data, $conversation);

        $systemMessage = $conversation->messages()->create([
            'sender_id'    => $createdBy,
            'message'      => "{$creator->name} created the group",
            'message_type' => 'system',
        ]);

        $conversation->load([
            'participants' => fn($q) => $q->where('is_active', true)->with('user'),
            'lastMessage.sender',
            'groupSetting',
        ]);

        $conversation->setRelation('unread_count', 0);

        event(new MessageEvent('sent', $conversation->id, $systemMessage->toArray()));

        $userModel = config('laravel-chat.user_model');
        foreach ($participants as $p) {
            $targetUser           = $userModel::find($p['user_id']);
            $conversationResource = (new ConversationResource($conversation))->forUser($targetUser)->toArray(request());
            event(new ConversationEvent($conversation, 'added', $p['user_id'], $conversationResource));
        }

        return new ConversationResource($conversation);
    }

    public function deleteForUser(int $userId, int $conversationId): bool
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (! $participant) {
            throw new HttpResponseException($this->error(null, 'Conversation not found or already removed.', 404));
        }

        $lastMsgId = $participant->conversation->lastMessage?->id;

        $participant->update([
            'is_active'               => false,
            'last_deleted_message_id' => $lastMsgId,
            'deleted_at'              => now(),
        ]);

        return true;
    }

    public function getMembers(int $userId, int $conversationId)
    {
        if (! $this->canUserPermit($conversationId, $userId)) {
            throw new HttpResponseException($this->error(null, 'Access denied.', 403));
        }

        return $this->find($conversationId)->participants()->active()->with('user')->get();
    }

    public function addMembers(Model $adder, int $conversationId, array $memberIds)
    {
        if (! $this->canUserManageMembers($conversationId, $adder->id)) {
            throw new HttpResponseException($this->error(null, 'Only admins can add members.', 403));
        }

        $conversation = $this->find($conversationId);
        $memberIds    = collect($memberIds)->unique()->reject(fn($id) => $id == $adder->id)->values();

        if ($memberIds->isEmpty()) {
            return ['members' => [], 'message' => null, 'conversation_id' => $conversationId];
        }

        $userModel            = config('laravel-chat.user_model');
        $users                = $userModel::whereIn('id', $memberIds)->get()->keyBy('id');
        $existingParticipants = $conversation->participants()->whereIn('user_id', $memberIds)->get()->keyBy('user_id');

        $now            = now();
        $addedMembers   = [];
        $systemMessages = [];

        foreach ($memberIds as $id) {
            $user        = $users->get($id);
            if (! $user) continue;

            $participant = $existingParticipants->get($id);
            $wasAdded    = false;
            $action      = null;

            if ($participant) {
                if ($participant->removed_at || $participant->left_at || ! $participant->is_active) {
                    $participant->update(['is_active' => true, 'removed_at' => null, 'left_at' => null]);
                    $wasAdded = true;
                    $action   = 're-added';
                }
            } else {
                $participant = $conversation->participants()->create([
                    'user_id'   => $id,
                    'is_active' => true,
                    'removed_at'=> null,
                    'left_at'   => null,
                ]);
                $wasAdded = true;
                $action   = 'added';
            }

            if (! $wasAdded) continue;

            $addedMembers[] = ['id' => $user->id, 'name' => $user->name, 'role' => 'member'];

            $systemMessages[] = [
                'conversation_id' => $conversationId,
                'sender_id'       => $adder->id,
                'message'         => "{$adder->name} {$action} {$user->name} to the conversation",
                'message_type'    => 'system',
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        if (! empty($systemMessages)) {
            Message::insert($systemMessages);
        }

        $conversation->load(['participants.user', 'lastMessage.sender', 'lastMessage.attachments', 'creator:id,name', 'groupSetting', 'activeInvites']);

        foreach ($addedMembers as $member) {
            $targetUser           = $users->get($member['id']);
            $conversationResource = (new ConversationResource($conversation))->forUser($targetUser)->toArray(request());
            event(new ConversationEvent($conversation, 'added', $targetUser->id, $conversationResource));
        }

        if (! empty($addedMembers)) {
            event(new ConversationEvent($conversation, 'member_added', null, ['added_by' => $adder->name, 'members' => $addedMembers]));
        }

        return ['members' => $addedMembers, 'message' => last($systemMessages), 'conversation_id' => $conversationId];
    }

    public function removeMember(int $actorId, int $conversationId, array $memberIds)
    {
        if (! $this->canUserManageMembers($conversationId, $actorId)) {
            throw new HttpResponseException($this->error(null, 'Only admins can remove members.', 403));
        }

        $conversation   = $this->find($conversationId);
        $actor          = $this->findUser($actorId);
        $participants   = $conversation->participants()->whereIn('user_id', $memberIds)->where('is_active', true)->get();
        $removedMembers = [];
        $lastMessage    = null;

        foreach ($participants as $participant) {
            $participant->update(['is_active' => false, 'removed_at' => now()]);
            $user             = $this->findUser($participant->user_id);
            $removedMembers[] = ['id' => $user->id, 'name' => $user->name];

            $lastMessage = $conversation->messages()->create([
                'sender_id'    => $actorId,
                'message'      => "{$actor->name} removed {$user->name} from the conversation",
                'message_type' => 'system',
            ]);

            event(new MessageEvent('sent', $conversation->id, $lastMessage->toArray()));
            event(new ConversationEvent($conversation, 'removed', $user->id));
        }

        return $this->success(['members' => $removedMembers, 'message' => $lastMessage], 'Members removed.');
    }

    public function addGroupAdmins(Model $actor, int $conversationId, array $userIds)
    {
        if (! $this->canUserManageMembers($conversationId, $actor->id)) {
            throw new HttpResponseException($this->error(null, 'Only admins can add admins.', 403));
        }

        ConversationParticipant::where('conversation_id', $conversationId)
            ->whereIn('user_id', $userIds)
            ->where('role', 'member')
            ->update(['role' => 'admin']);

        event(new ConversationEvent($this->find($conversationId), 'admin_added'));
    }

    public function removeGroupAdmins(Model $actor, int $conversationId, array $userIds)
    {
        if (! $this->canUserManageMembers($conversationId, $actor->id)) {
            throw new HttpResponseException($this->error(null, 'Only admins can remove admins.', 403));
        }

        $conversation  = $this->find($conversationId);
        $participants  = ConversationParticipant::where('conversation_id', $conversationId)->whereIn('user_id', $userIds)->where('role', 'admin')->get();
        $updatedMembers = [];

        foreach ($participants as $participant) {
            $participant->update(['role' => 'member']);
            $user = $this->findUser($participant->user_id);
            $updatedMembers[] = ['id' => $user->id, 'role' => 'member'];
            $conversation->messages()->create([
                'sender_id'    => $actor->id,
                'message'      => "{$actor->name} removed admin rights from {$user->name}",
                'message_type' => 'system',
            ]);
        }

        event(new ConversationEvent($conversation, 'admin_removed', null, ['added_by' => $actor->name, 'members' => $participants]));

        return ['members' => $updatedMembers];
    }

    public function leaveGroup(Model $user, int $conversationId): bool
    {
        $conversation = $this->find($conversationId);

        $conversation->participants()->where('user_id', $user->id)->update(['is_active' => false, 'left_at' => now()]);

        $systemMessage = $conversation->messages()->create([
            'sender_id'    => $user->id,
            'message'      => $user->name . ' left the conversation',
            'message_type' => 'system',
        ]);

        event(new MessageEvent('sent', $systemMessage->conversation_id, $systemMessage->toArray()));
        event(new ConversationEvent($conversation, 'left', $user->id));
        event(new ConversationEvent($conversation->fresh(), 'member_left', null, ['left_user_id' => $user->id, 'left_user_name' => $user->name]));

        return true;
    }

    public function muteGroup(int $userId, int $conversationId, int $minutes = 0): bool
    {
        $participant = $this->find($conversationId)->participants()->where('user_id', $userId)->firstOrFail();

        if ($minutes === -1) {
            $participant->update(['is_muted' => true, 'muted_until' => null]);
            return true;
        } elseif ($minutes > 0) {
            $participant->update(['is_muted' => true, 'muted_until' => now()->addMinutes($minutes)]);
            return true;
        } else {
            $participant->update(['is_muted' => false, 'muted_until' => null]);
            return false;
        }
    }

    public function pinToggleMessage(Model $user, Message $message): array
    {
        $message->update(['is_pinned' => ! $message->is_pinned]);
        $conversation  = $message->conversation;
        $systemMessage = $conversation->messages()->create([
            'sender_id'    => $user->id,
            'message'      => $user->name . ($message->is_pinned ? ' pinned a message' : ' unpinned a message'),
            'message_type' => 'system',
        ]);

        event(new MessageEvent('sent', $systemMessage->conversation_id, $systemMessage->toArray()));
        event(new MessageEvent(($message->is_pinned ? 'pinned' : 'unpinned'), $systemMessage->conversation_id, $message->toArray()));

        return ['message' => $message, 'last_message' => $systemMessage];
    }

    public function updateGroupInfo(int $userId, int $conversationId, array $data)
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)->where('user_id', $userId)->firstOrFail();
        $setting     = GroupSettings::where('conversation_id', $conversationId)->firstOrFail();

        if (! $setting->allow_members_to_change_group_info && ! in_array($participant->role, ['admin', 'super_admin'])) {
            throw new HttpResponseException($this->error(null, 'Only admins can update group info.', 403));
        }

        $conversation = $this->find($conversationId);
        $conversation->update(['name' => $data['name'] ?? $conversation->name]);

        if (isset($data['group'])) {
            if (isset($data['group']['avatar'])) {
                chat_delete_file($conversation->groupSetting->avatar);
                $data['group']['avatar'] = chat_upload_file($data['group']['avatar'], config('laravel-chat.uploads.group_avatar_path'));
            }
            $conversation->groupSetting()->update($data['group']);
        }

        $conversation = $conversation->fresh()->load('groupSetting');

        event(new ConversationEvent($conversation, 'updated', null, [
            'group_setting' => $conversation->groupSetting,
            'avatar'        => optional($conversation->groupSetting)->avatar,
        ]));

        return $conversation;
    }

    public function deleteGroup(int $conversationId): bool
    {
        $conversation = $this->find($conversationId);
        $conversation->delete();
        event(new ConversationEvent($conversation, 'deleted'));
        return true;
    }

    public function createDefault(int $conversationId): GroupSettings
    {
        return GroupSettings::create(array_merge(
            config('laravel-chat.group_defaults', []),
            ['conversation_id' => $conversationId]
        ));
    }

    public function acceptInvite(Model $user, string $token)
    {
        $invite = ConversationInvite::where('token', $token)->firstOrFail();

        if ($invite->expires_at && now()->gt($invite->expires_at)) {
            throw new HttpResponseException($this->error(null, 'Invite expired.', 403));
        }

        if ($invite->max_uses && $invite->used_count >= $invite->max_uses) {
            throw new HttpResponseException($this->error(null, 'Invite limit reached.', 403));
        }

        if (! $this->canUserInviteViaLink($invite->conversation_id)) {
            throw new HttpResponseException($this->error(null, 'Invite links are disabled for this group.', 403));
        }

        $invite->increment('used_count');

        return $this->addMembers($this->findUser($invite->created_by), $invite->conversation_id, [$user->id]);
    }

    public function regenerateInvite(Model $user, array $data, int $conversationId)
    {
        if (! $this->canUserInviteViaLink($conversationId)) {
            throw new HttpResponseException($this->error(null, 'Invite links are disabled for this group.', 403));
        }

        $conversation = $this->find($conversationId);
        $conversation->invites()->update(['is_active' => false]);

        return $this->createInviteLink($user, $data, $conversation);
    }

    public function createInviteLink(Model $user, array $data, Conversation $conversation): array
    {
        $invite = $conversation->invites()->create([
            'token'      => bin2hex(random_bytes(12)),
            'created_by' => $user->id,
            'expires_at' => $data['expires_at'] ?? null,
            'max_uses'   => $data['max_uses'] ?? null,
        ]);

        $baseUrl = config('laravel-chat.invite_url', config('app.url') . '/api/v1/accept-invite');

        return ['invite_link' => $baseUrl . '/' . $invite->token];
    }

    public function toggleBlock(Model $user, int $userId): bool
    {
        if ($user->id === $userId) {
            throw new HttpResponseException($this->error(null, 'You cannot block yourself.', 422));
        }

        $user->blockedUsers()->toggle($userId);
        $isBlocked    = $user->blockedUsers()->where('users.id', $userId)->exists();
        $conversation = $this->findPrivateBetween($user->id, $userId);

        if ($conversation) {
            event(new ConversationEvent($conversation, $isBlocked ? 'blocked' : 'unblocked', $userId));
        }

        return $isBlocked;
    }

    public function toggleRestrict(Model $user, int $userId): bool
    {
        if ($user->id === $userId) {
            throw new HttpResponseException($this->error(null, 'You cannot restrict yourself.', 422));
        }

        $user->restrictedUsers()->toggle($userId);

        return $user->restrictedUsers()->where('restricted_id', $userId)->exists();
    }

    // -------------------------------------------------------------------------
    // Permission helpers
    // -------------------------------------------------------------------------

    public function canUserPermit(int $conversationId, int $userId): bool
    {
        return ConversationParticipant::where('conversation_id', $conversationId)->where('user_id', $userId)->exists();
    }

    public function canGroupDeletePermit(int $userId, int $conversationId): bool
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)->where('user_id', $userId)->firstOrFail();

        if ($participant->role !== 'super_admin') {
            throw new HttpResponseException($this->error(null, 'Only super admins can delete the group.', 403));
        }

        return true;
    }

    public function canUserManageMembers(int $conversationId, int $userId): bool
    {
        $setting = GroupSettings::where('conversation_id', $conversationId)->first();

        if (! $setting || $setting->allow_members_to_add_remove_participants) {
            return true;
        }

        return ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereIn('role', ['admin', 'super_admin'])
            ->exists();
    }

    public function canUserInviteViaLink(int $conversationId): bool
    {
        $setting = GroupSettings::where('conversation_id', $conversationId)->first();

        return ! $setting || $setting->allow_invite_users_via_link;
    }
}
