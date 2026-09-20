<?php

declare(strict_types=1);

namespace App\Chat\Exceptions;

use RuntimeException;

/**
 * Raised when an upstream AI provider cannot produce a reply.
 *
 * The message is developer-facing and logged; it is never shown to end users.
 */
final class ChatProviderException extends RuntimeException
{
    /**
     * Upstream statuses that mean "this model cannot serve you", as opposed to
     * "this request was wrong". Free-tier quota is counted per model, so another
     * model may still answer.
     */
    private const TRY_ANOTHER_MODEL = [404, 429];

    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }

    public static function missingCredentials(string $provider): self
    {
        return new self("No API key configured for the [{$provider}] chat provider.");
    }

    public static function requestFailed(string $provider, int $status, string $body): self
    {
        return new self("The [{$provider}] provider returned HTTP {$status}: ".mb_substr($body, 0, 500), $status);
    }

    public static function unreachable(string $provider, string $reason): self
    {
        return new self("Could not reach the [{$provider}] provider: {$reason}");
    }

    public static function emptyReply(string $provider, string $detail = ''): self
    {
        return new self(trim("The [{$provider}] provider returned an empty reply. {$detail}"));
    }

    /**
     * Whether a different model is worth trying for the same request.
     */
    public function suggestsAnotherModel(): bool
    {
        return in_array($this->status, self::TRY_ANOTHER_MODEL, true);
    }

    /**
     * Whether the model is out of quota rather than merely missing.
     */
    public function isQuotaExhausted(): bool
    {
        return $this->status === 429;
    }
}
