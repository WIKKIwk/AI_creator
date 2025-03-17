<?php

namespace App\Http\Requests;

use App\Enums\LearnStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class WordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'word.store', 'word.update' => [
                'word' => 'required|string',
                'meaning' => 'nullable|string',
                'synonyms' => 'nullable|string',
                'antonyms' => 'nullable|string',
                'is_favorite' => 'nullable|boolean',
                'category_id' => 'nullable|integer|exists:categories,id,user_id,' . auth('sanctum')->id(),
            ],
            'word.move'                 => [
                'category_id' => 'required|integer|exists:categories,id,user_id,' . auth('sanctum')->id(),
            ],
            'word.progress'                 => [
                'learn_status' => 'required|integer|in:' . implode(',', LearnStatus::values()),
            ],
            default                     => [],
        };
    }
}
