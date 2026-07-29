<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\CommentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    use CommentValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is settled by the policy in the controller, which can tell a
     * missing comment from one that belongs to somebody else.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'text' => $this->commentTextRules(),
        ];
    }
}
