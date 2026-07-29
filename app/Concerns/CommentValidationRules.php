<?php

namespace App\Concerns;

use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;

trait CommentValidationRules
{
    /**
     * Get the validation rules used to validate comment bodies.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function commentTextRules(): array
    {
        return ['required', 'string', 'min:1', 'max:'.Comment::MAX_TEXT_LENGTH];
    }
}
