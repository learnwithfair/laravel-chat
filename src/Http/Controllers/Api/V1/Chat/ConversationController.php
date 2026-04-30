<?php

namespace RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use RahatulRabbi\TalkBridge\Services\ChatService;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;

class ConversationController extends Controller
{
    use ApiResponse;

    public function __construct(protected ChatService $chatService) {}

    public function index(Request $request)
    {
        $perPage       = (int) $request->get('per_page', config('laravel-chat.pagination.conversations', 30));
        $conversations = $this->chatService->listConversations(Auth::user(), $perPage, $request->query('q'));
        return $this->success($conversations, 'Conversations fetched successfully.', 200, true);
    }

    public function mediaLibrary(Request $request, int $conversationId)
    {
        $perPage      = (int) $request->get('per_page', config('laravel-chat.pagination.media', 30));
        $mediaLibrary = $this->chatService->mediaLibrary($request->user(), $conversationId, $perPage);
        return $this->success($mediaLibrary, 'Media library fetched successfully.');
    }

    public function startPrivateConversation(Request $request)
    {
        $conversation = $this->chatService->startConversation(Auth::user(), $request->receiver_id);
        return $this->success($conversation, 'Conversation created successfully.', 201);
    }

    public function store(Request $request)
    {
        $group = $this->chatService->createGroup(Auth::user(), $request->all());
        return $this->success($group, 'Group created successfully.', 201);
    }

    public function destroy(Request $request, int $conversation)
    {
        $this->chatService->deleteConversationForUser(Auth::id(), $conversation);
        return $this->success(null, 'Conversation removed from your list.', 200);
    }
}
