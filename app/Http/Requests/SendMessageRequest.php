<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single user message before it reaches the AI provider.
 */
final class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:'.$this->maxLength()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Please type a message first.',
            'message.max' => 'Messages are limited to '.$this->maxLength().' characters.',
        ];
    }

    /**
     * Collapse surrounding whitespace so blank submissions fail validation.
     */
    protected function prepareForValidation(): void
    {
        $message = $this->input('message');

        if (is_string($message)) {
            $this->merge(['message' => trim($message)]);
        }
    }

    public function message(): string
    {
        return (string) $this->validated('message');
    }

    private function maxLength(): int
    {
        return (int) config('chatbot.max_message_length', 4000);
    }
}
