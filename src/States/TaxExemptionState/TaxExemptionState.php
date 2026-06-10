<?php

declare(strict_types=1);

namespace AIArmada\Tax\States\TaxExemptionState;

use AIArmada\Tax\Models\TaxExemption;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method TaxExemption getModel()
 */
abstract class TaxExemptionState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    abstract public function icon(): string;

    public function isPending(): bool
    {
        return false;
    }

    public function isTerminal(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new TaxExemption;

        $options = [];

        /** @var class-string<TaxExemptionState> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    /**
     * Called after state transition to auto-set lifecycle timestamps.
     */
    public function recordTransition(): void
    {
        $model = $this->getModel();
        $now = CarbonImmutable::now();

        match (true) {
            $this instanceof ApprovedState => $model->verified_at ??= $now,
            $this instanceof RevokedState => $model->revoked_at ??= $now,
            default => null,
        };
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, UnderReviewState::class)
            ->allowTransition(PendingState::class, RejectedState::class)
            ->allowTransition(PendingState::class, RevokedState::class)
            ->allowTransition(UnderReviewState::class, ApprovedState::class)
            ->allowTransition(UnderReviewState::class, RejectedState::class)
            ->allowTransition(UnderReviewState::class, RevokedState::class)
            ->allowTransition(ApprovedState::class, ExpiredState::class)
            ->allowTransition(ApprovedState::class, RevokedState::class);
    }
}
