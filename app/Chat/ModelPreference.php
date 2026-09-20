<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Support\ModelChoice;
use Illuminate\Contracts\Session\Session;

/**
 * The visitor's chosen model, held in the session.
 *
 * Only models named in configuration can ever be selected, so a client cannot
 * steer the app at an arbitrary upstream model.
 */
final readonly class ModelPreference
{
    private const SESSION_KEY = 'chat.model';

    /**
     * @param  list<string>  $allowed  Configured models, preferred first.
     */
    public function __construct(private array $allowed) {}

    /**
     * @return list<string>
     */
    public function allowed(): array
    {
        return $this->allowed;
    }

    public function allows(string $model): bool
    {
        return in_array($model, $this->allowed, true);
    }

    /**
     * The chosen model, falling back to the configured default.
     */
    public function current(Session $session): string
    {
        $chosen = $session->get(self::SESSION_KEY);

        return is_string($chosen) && $this->allows($chosen)
            ? $chosen
            : ($this->allowed[0] ?? '');
    }

    /**
     * Records a choice. Returns false when the model is not on the list.
     */
    public function choose(Session $session, string $model): bool
    {
        if (! $this->allows($model)) {
            return false;
        }

        $session->put(self::SESSION_KEY, $model);

        return true;
    }

    /**
     * @return list<ModelChoice>
     */
    public function options(): array
    {
        return ModelChoice::listFrom($this->allowed);
    }
}
