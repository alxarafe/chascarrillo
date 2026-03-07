<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasWorkflow.
 *
 * Provides a basic state machine implementation for models that require
 * draft -> validated -> published lifecycles (typical blog functionality).
 * 
 * Re-implemented locally in Chascarrillo after being extracted from Alxarafe core.
 */
trait HasWorkflow
{
    /**
     * Define the states and transitions for the model.
     * Must be implemented by the using class.
     *
     * @return array
     */
    abstract protected function getWorkflowDefinition(): array;

    /**
     * Obtiene los estados permitidos.
     */
    public static function getStates(): array
    {
        /** @phpstan-ignore new.static */
        return (new static())->getWorkflowDefinition()['states'] ?? [];
    }

    /**
     * Get the current state ID of the model.
     * Assumes a 'status' or 'state' column. Override if using a different column name.
     */
    public function getCurrentState(): int
    {
        return (int) $this->status;
    }

    /**
     * Check if a transition to the target state is allowed from the current state.
     */
    public function canTransition(int $targetStateId): bool
    {
        $currentState = $this->getCurrentState();
        $definition = $this->getWorkflowDefinition();

        if (isset($definition['transitions'])) {
            foreach ($definition['transitions'] as $transition) {
                if ($transition['to'] === $targetStateId && in_array($currentState, $transition['from'], true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Execute a transition to a new state if allowed.
     */
    public function transition(int $targetStateId): bool
    {
        if ($this->canTransition($targetStateId)) {
            $this->status = $targetStateId;
            return $this->save();
        }
        return false;
    }

    /**
     * Get the label for the current state.
     */
    public function getCurrentStateLabel(): string
    {
        $currentState = $this->getCurrentState();
        $definition = $this->getWorkflowDefinition();

        if (isset($definition['states'][$currentState])) {
            return $definition['states'][$currentState];
        }

        return (string) $currentState;
    }

    /**
     * Get a list of available transitions (target states) from the current state.
     * Returns an array of associative arrays with 'id' and 'label'.
     */
    public function getAvailableTransitions(): array
    {
        $currentState = $this->getCurrentState();
        $definition = $this->getWorkflowDefinition();
        $available = [];

        if (isset($definition['transitions'])) {
            foreach ($definition['transitions'] as $name => $transition) {
                if (in_array($currentState, $transition['from'], true)) {
                    $targetId = $transition['to'];
                    $label = $definition['states'][$targetId] ?? (string) $targetId;
                    
                    // Allow translation keys or fallback to the registered text
                    if (class_exists('\Alxarafe\Lib\Trans')) {
                        $label = (string) \Alxarafe\Lib\Trans::_('transition_' . $name);
                        if ($label === 'transition_' . $name) {
                            $label = ucfirst($name); // Fallback if no translation
                        }
                    }

                    $available[] = [
                        'id' => $targetId,
                        'name' => $name,
                        'label' => $label,
                    ];
                }
            }
        }

        return $available;
    }

    // --- Scopes ---

    /**
     * Scope a query to only include models in a specific state.
     */
    public function scopeInState(Builder $query, int $stateId): Builder
    {
        return $query->where('status', $stateId);
    }

    /**
     * Scope a query to only include draft models (assuming 0 is draft).
     */
    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('status', 0);
    }
}
