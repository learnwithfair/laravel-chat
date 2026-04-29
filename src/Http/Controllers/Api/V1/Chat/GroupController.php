<?php

namespace RahatulRabbi\LaravelChat\Http\Controllers\Api\V1\Chat;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use RahatulRabbi\LaravelChat\Http\Requests\Chat\CreateInviteRequest;
use RahatulRabbi\LaravelChat\Http\Requests\Chat\ManageGroupAdminRequest;
use RahatulRabbi\LaravelChat\Http\Requests\Chat\UpdateGroupInfoRequest;
use RahatulRabbi\LaravelChat\Services\ChatService;
use RahatulRabbi\LaravelChat\Traits\ApiResponse;

class GroupController extends Controller
{
    use ApiResponse;

    public function __construct(protected ChatService $chatService) {}

    public function addMembers(ManageGroupAdminRequest $request, int $conversationId)
    {
        $result = $this->chatService->addMembers(Auth::user(), $conversationId, $request->member_ids);
        return $this->success($result, 'Members added successfully.');
    }

    public function acceptInvite(Request $request, string $token)
    {
        $result = $this->chatService->acceptInvite($request->user(), $token);
        return $this->success($result, 'Successfully joined the group.');
    }

    public function regenerateInvite(CreateInviteRequest $request, int $conversationId)
    {
        $result = $this->chatService->regenerateInvite($request->user(), $request->validated(), $conversationId);
        return $this->success($result, 'Invite regenerated successfully.');
    }

    public function getMembers(Request $request, int $conversationId)
    {
        $result = $this->chatService->getMembers($request->user(), $conversationId);
        return $this->success($result, 'Members fetched successfully.');
    }

    public function removeMember(ManageGroupAdminRequest $request, int $conversationId)
    {
        $result = $this->chatService->removeMember(Auth::user(), $conversationId, $request->member_ids);
        return $this->success($result, 'Members removed successfully.');
    }

    public function addAdmins(ManageGroupAdminRequest $request, int $conversationId)
    {
        $result = $this->chatService->addGroupAdmins(Auth::user(), $conversationId, $request->member_ids);
        return $this->success($result, 'Admins added successfully.');
    }

    public function removeAdmins(ManageGroupAdminRequest $request, int $conversationId)
    {
        $result = $this->chatService->removeGroupAdmins(Auth::user(), $conversationId, $request->member_ids);
        return $this->success($result, 'Admins removed successfully.');
    }

    public function muteToggleGroup(Request $request, int $conversationId)
    {
        $request->validate(['minutes' => 'nullable|integer']);
        $result = $this->chatService->muteGroup(Auth::user(), $conversationId, $request->minutes ?? 0);
        return $this->success(null, $result ? 'Group muted successfully.' : 'Group unmuted successfully.');
    }

    public function leaveGroup(int $conversationId)
    {
        $this->chatService->leaveGroup(Auth::user(), $conversationId);
        return $this->success(null, 'Left the group successfully.');
    }

    public function deleteGroup(int $conversationId)
    {
        $this->chatService->deleteGroup(Auth::user(), $conversationId);
        return $this->success(null, 'Group deleted successfully.');
    }

    public function update(UpdateGroupInfoRequest $request, int $conversation)
    {
        $group = $this->chatService->updateGroupInfo(Auth::user(), $conversation, $request->validated());
        return $this->success($group, 'Group updated successfully.');
    }
}
