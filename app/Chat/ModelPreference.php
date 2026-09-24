<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Support\ModelChoice;
use Illuminate\Contracts\Session\Session;
use InvalidArgumentException;

/**
 * The visitor's chosen provider and model, held in the session.
 *
 * Only models named in configuration can ever be selected, so a client cannot
 * steer the app at an arbitrary upstream model or provider.
 */
final readonly class ModelPreference
{
    private const SESSION_KEY = 'chat.model';

    /**
     * @param  list<ModelChoice>  $choices  Every selectable model, the default first.
     */
    public function __construct(private array $choices)
    {
        if ($choices === []) {
            throw new InvalidArgumentException('At least one selectable model is required.');
        }
    }

    /**
     * @return list<string>
     */
    public function allowed(): array
    {
        return array_map(static fn (ModelChoice $choice): string => $choice->id, $this->choices);
    }

    public function allows(string $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * The chosen model's id, falling back to the configured default.
     */
    public function current(Session $session): string
    {
        return $this->currentChoice($session)->id;
    }

    public function currentChoice(Session $session): ModelChoice
    {
        $chosen = $session->get(self::SESSION_KEY);

        return (is_string($chosen) ? $this->find($chosen) : null) ?? $this->choices[0];
    }

    /**
     * Records a choice. Returns false when the model is not on the list.
     */
    public function choose(Session $session, string $id): bool
    {
        if (! $this->allows($id)) {
            return false;
        }

        $session->put(self::SESSION_KEY, $id);

        return true;
    }

    /**
     * @return list<ModelChoice>
     */
    public function options(): array
    {
        return $this->choices;
    }

    /**
     * The choices grouped by provider, in the order providers were configured.
     *
     * @return array<string, list<ModelChoice>>
     */
    public function groupedOptions(): array
    {
        $groups = [];

        foreach ($this->choices as $choice) {
            $groups[$choice->provider][] = $choice;
        }

        return $groups;
    }

    private function find(string $id): ?ModelChoice
    {
        foreach ($this->choices as $choice) {
            if ($choice->id === $id) {
                return $choice;
            }
        }

        return null;
    }
}
