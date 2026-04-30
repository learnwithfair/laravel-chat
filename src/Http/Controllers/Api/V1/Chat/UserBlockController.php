<?php
namespace RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use RahatulRabbi\TalkBridge\Services\ChatService;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;
class UserBlockController extends Controller {
    use ApiResponse;
    public function __construct(protected ChatService $chatService) {}
    public function index(Request $request) {
        $userModel = config('laravel-chat.user_model');
        $avatarField = config('laravel-chat.user_fields.avatar', 'avatar_path');
        $users = $userModel::when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->where('id', '!=', Auth::id())
            ->select('id', 'name', $avatarField)
            ->paginate(config('laravel-chat.pagination.users', 20));
        return $this->success($users, 'Users fetched.', 200, true);
    }
    public function onlineUsers() {
        $userModel = config('laravel-chat.user_model');
        $lastSeenField = config('laravel-chat.user_fields.last_seen', 'last_seen_at');
        $threshold = config('laravel-chat.online_threshold_minutes', 2);
        $users = $userModel::whereNotNull($lastSeenField)
            ->where($lastSeenField, '>', now()->subMinutes($threshold))
            ->where('id', '!=', Auth::id())
            ->get();
        return $this->success($users, 'Online users fetched.');
    }
    public function toggleBlock(Request $request, int $user) {
        $result = $this->chatService->toggleBlock(Auth::user(), $user);
        return $this->success($result, $result ? 'User blocked.' : 'User unblocked.');
    }
    public function toggleRestrict(Request $request, int $user) {
        $result = $this->chatService->toggleRestrict(Auth::user(), $user);
        return $this->success($result, $result ? 'User restricted.' : 'User unrestricted.');
    }
}
