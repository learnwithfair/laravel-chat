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
        $avatarField = config('laravel-chat.user_fields.avatar', 'avatar_path');

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
                $isOnline      = $this->isUserOnline($receiver);
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
                    'id'      => $this->lastMessage->sender->id,
                    'name'    => $this->lastMessage->sender->name,
                    'avatar'  => $this->lastMessage->sender->{$avatarField} ?? null,
                    'is_online' => $this->isUserOnline($this->lastMessage->sender),
                    'last_seen' => $this->lastMessage->sender->{config('laravel-chat.user_fields.last_seen', 'last_seen_at')}?->diffForHumans(),
                ],
                'created_at'  => $this->lastMessage->created_at->toDateTimeString(),
            ] : null,
            'participants'  => $this->participants->take(3)->map(fn($p) => [
                'id'        => $p->user_id,
                'name'      => $p->user->name,
                'role'      => $p->role,
                'avatar'    => $p->user->{$avatarField} ?? null,
                'is_muted'  => $p->is_muted,
                'is_online' => $this->isUserOnline($p->user),
            ]),
            'receiver'      => $receiver ? [
                'id'        => $receiver->id,
                'name'      => $receiver->name,
                'avatar'    => $receiver->{$avatarField} ?? null,
                'is_online' => $isOnline,
                'last_seen' => $receiver->{config('laravel-chat.user_fields.last_seen', 'last_seen_at')}?->diffForHumans(),
            ] : null,
            'is_online'     => $isOnline,
            'is_blocked'    => $isBlocked,
            'blocked'       => ['by_me' => $blockedByMe, 'by_them' => $blockedByThem],
            'unread_count'  => $this->unread_count ?? 0,
            'is_admin'      => in_array($participant?->role, ['admin', 'super_admin']),
            'role'          => $participant?->role,
            'is_muted'      => $participant?->is_muted,
            'group_setting' => $this->groupSetting,
            'can_send_message' => $this->canUserSendMessage($participant),
            'invite_link'   => $this->inviteLink
                ? config('laravel-chat.invite_url') . '/' . $this->inviteLink->token
                : null,
            'created_by'    => $this->creator->name ?? null,
            'created_at'    => $this->created_at->toDateTimeString(),
            'updated_at'    => $this->updated_at->toDateTimeString(),
        ];
    }

    protected function isUserOnline(Model $user): bool
    {
        $field     = config('laravel-chat.user_fields.last_seen', 'last_seen_at');
        $threshold = config('laravel-chat.online_threshold_minutes', 2);

        return $user->{$field} && $user->{$field}->greaterThan(now()->subMinutes($threshold));
    }
}
