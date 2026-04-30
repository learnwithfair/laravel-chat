<?php
namespace RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use RahatulRabbi\TalkBridge\Services\ChatService;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;
class ReactionController extends Controller {
    use ApiResponse;
    public function __construct(protected ChatService $chatService) {}
    public function index(int $messageId) {
        return $this->success($this->chatService->listReactions($messageId), 'Reactions fetched.');
    }
    public function toggleReaction(Request $request, int $messageId) {
        $request->validate(['reaction' => 'required|string']);
        $reaction = $this->chatService->toggleReaction(Auth::user(), $messageId, $request->reaction);
        return $this->success($reaction, 'Reaction updated.');
    }
}
