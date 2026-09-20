<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Chat\ModelPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a model selection against the configured whitelist.
 */
final class ChooseModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'model' => ['required', 'string', Rule::in(app(ModelPreference::class)->allowed())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['model.in' => 'That model is not available.'];
    }

    public function model(): string
    {
        return (string) $this->validated('model');
    }
}
