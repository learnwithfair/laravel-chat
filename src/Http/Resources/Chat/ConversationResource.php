<?php

namespace RahatulRabbi\TalkBridge\Http\Resources\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    protected ?Model $forUser = null;

    public function forUser(Model $user): static
    {
        $this->forUser = $user;
        return $this;
    }

    public function toArray($request): array
    {
        $authUser    = $this->forUser ?? $request->user();
        $participant = $this->participants->firstWhere('user_id', $authUser->id);

        $receiver      = null;
        $isBlocked     = false;
        $isOnline      = false;
        $blockedByMe   = false;
        $blockedByThem = false;

        if ($this->type === 'private') {
            $receiver = $this->otherParticipant($authUser);

            if ($receiver && $authUser) {
                $blockedByMe   = $authUser->hasBlocked($receiver);
                $blockedByThem = $receiver->hasBlocked($authUser);
                $isBlocked     = $blockedByMe || $blockedByThem;
                $isOnline      = $receiver->isOnline();
            }
        }

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'type'          => $this->type,

            'last_message'  => $this->lastMessage ? [
                'id'          => $this->lastMessage->id,
                'message'     => $this->lastMessage->message,
                'attachments' => MessageAttachmentResource::collection($this->lastMessage->attachments),
                'sender'      => [
                    'id'        => $this->lastMessage->sender->id,
                    'name'      => talkbridge_user_name($this->lastMessage->sender),
                    'avatar'    => talkbridge_user_avatar($this->lastMessage->sender),
                    'is_online' => $this->lastMessage->sender->isOnline(),
                    'last_seen' => $this->lastMessage->sender->getChatLastSeen(),
                ],
                'created_at'  => $this->lastMessage->created_at->toDateTimeString(),
            ] : null,

            'participants'  => $this->participants->take(3)->map(fn($p) => [
                'id'        => $p->user_id,
                'name'      => talkbridge_user_name($p->user),
                'role'      => $p->role,
                'avatar'    => talkbridge_user_avatar($p->user),
                'is_muted'  => $p->is_muted,
                'is_online' => $p->user->isOnline(),
            ]),

            'receiver'      => $receiver ? [
                'id'        => $receiver->id,
                'name'      => talkbridge_user_name($receiver),
                'avatar'    => talkbridge_user_avatar($receiver),
                'is_online' => $isOnline,
                'last_seen' => $receiver->getChatLastSeen(),
            ] : null,

            'is_online'        => $isOnline,
            'is_blocked'       => $isBlocked,
            'blocked'          => ['by_me' => $blockedByMe, 'by_them' => $blockedByThem],
            'unread_count'     => $this->unread_count ?? 0,
            'is_admin'         => in_array($participant?->role, ['admin', 'super_admin']),
            'role'             => $participant?->role,
            'is_muted'         => $participant?->is_muted,
            'group_setting'    => $this->groupSetting,
            'can_send_message' => $this->canUserSendMessage($participant),
            'invite_link'      => $this->inviteLink
                ? config('talkbridge.invite_url') . '/' . $this->inviteLink->token
                : null,
            'created_by'       => talkbridge_user_name($this->creator) ?? null,
            'created_at'       => $this->created_at->toDateTimeString(),
            'updated_at'       => $this->updated_at->toDateTimeString(),
        ];
    }
}
