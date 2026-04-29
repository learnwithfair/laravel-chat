<?php

namespace RahatulRabbi\LaravelChat\Http\Controllers\Api\V1\Chat;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use RahatulRabbi\LaravelChat\Http\Requests\Chat\DeleteMessageRequest;
use RahatulRabbi\LaravelChat\Http\Requests\Chat\SendMessageRequest;
use RahatulRabbi\LaravelChat\Models\Message;
use RahatulRabbi\LaravelChat\Services\ChatService;
use RahatulRabbi\LaravelChat\Traits\ApiResponse;

class MessageController extends Controller
{
    use ApiResponse;

    public function __construct(protected ChatService $chatService) {}

    public function show(Request $request, int $message)
    {
        $perPage  = $request->query('per_page', config('laravel-chat.pagination.messages', 20));
        $messages = $this->chatService->getMessages(Auth::user(), $message, $request->query('q'), $perPage);
        return $this->success($messages, 'Messages fetched successfully.', 200, true);
    }

    public function getAllPinnedMessages(Request $request, int $conversation)
    {
        $perPage = (int) $request->get('per_page', config('laravel-chat.pagination.pinned', 40));
        $pinned  = $this->chatService->pinnedMessages($request->user(), $conversation, $request->query('q'), $perPage);
        return $this->success($pinned, 'Pinned messages fetched successfully.', 200, true);
    }

    public function store(SendMessageRequest $request)
    {
        $message = $this->chatService->sendMessage(Auth::user(), $request->validated());
        return $this->success($message, 'Message sent successfully.', 201);
    }

    public function update(SendMessageRequest $request, Message $message)
    {
        $message = $this->chatService->updateMessage(Auth::user(), $request->validated(), $message);
        return $this->success($message, 'Message updated successfully.', 200);
    }

    public function pinToggleMessage(Request $request, Message $message)
    {
        $result = $this->chatService->pinToggleMessage($request->user(), $message);
        return $this->success($result, $result['message']->is_pinned ? 'Message pinned.' : 'Message unpinned.', 200);
    }

    public function deleteForMe(DeleteMessageRequest $request)
    {
        $result = $this->chatService->deleteForMe(Auth::user(), $request->validated());
        return $this->success($result, 'Messages deleted for you.', 200);
    }

    public function deleteForEveryone(DeleteMessageRequest $request)
    {
        $result = $this->chatService->deleteForEveryone(Auth::user(), $request->validated());
        return $this->success($result, 'Messages deleted for everyone.', 200);
    }

    public function markAsSeen(int $conversationId)
    {
        $this->chatService->markConversationAsRead(Auth::user(), $conversationId);
        return $this->success(null, 'Conversation marked as seen.');
    }

    public function markSeen(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'message_ids'     => 'required|array',
            'message_ids.*'   => 'integer|exists:messages,id',
        ]);
        $result = $this->chatService->markMessagesAsRead(Auth::user(), $request->all());
        return $this->success($result, 'Messages marked as seen.');
    }

    public function markAsDelivered(int $conversationId)
    {
        $this->chatService->markDelivered(Auth::user(), $conversationId);
        return $this->success(null, 'Conversation marked as delivered.');
    }

    public function forward(Request $request, Message $message)
    {
        $data = $request->validate([
            'conversation_ids'   => ['required', 'array', 'min:1'],
            'conversation_ids.*' => ['integer', 'exists:conversations,id'],
        ]);

        $results = [];

        foreach ($data['conversation_ids'] as $conversationId) {
            $results[] = $this->chatService->sendMessage($request->user(), [
                'conversation_id'       => $conversationId,
                'message'               => $message->message,
                'message_type'          => $message->message_type,
                'forward_to_message_id' => $message->id,
            ]);
        }

        return $this->success($results, 'Message forwarded successfully.', 201);
    }
}
