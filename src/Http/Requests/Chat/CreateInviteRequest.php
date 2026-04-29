<?php
namespace RahatulRabbi\LaravelChat\Http\Requests\Chat;
use RahatulRabbi\LaravelChat\Http\Requests\BaseRequest;
class CreateInviteRequest extends BaseRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_uses'   => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
