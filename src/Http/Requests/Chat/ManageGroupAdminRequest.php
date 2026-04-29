<?php
namespace RahatulRabbi\LaravelChat\Http\Requests\Chat;
use RahatulRabbi\LaravelChat\Http\Requests\BaseRequest;
class ManageGroupAdminRequest extends BaseRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'member_ids'   => 'required|array|min:1',
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
