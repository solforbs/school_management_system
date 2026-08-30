<?php

namespace App\Concerns;

use Illuminate\Support\Str;
use Tether\Client\MutationLogger;

/**
 * Marks a model as participating in offline sync.
 *
 * Deliberately *not* Tether's own Syncable trait: that one self-registers
 * the model under its real class name, but Tether resolves push mutations
 * through a single flat `model_namespace`. With models spread across
 * Modules\*\Models\*, the two names disagree and the per-model push guard
 * and conflict resolver silently stop firing while writes still apply.
 * TetherServiceProvider registers everything under one aliased namespace
 * instead, so both halves agree. See that provider for the detail.
 *
 * Models using this trait may declare:
 *
 *   protected array $tetherSyncable = ['title', 'amount'];
 *
 * to whitelist which fields an inbound client mutation may write. Omit it
 * and Laravel's $fillable is the only guard.
 */
trait TetherSyncable
{
    public static function bootTetherSyncable(): void
    {
        static::creating(function ($model): void {
            if (blank($model->tether_id)) {
                $model->tether_id = (string) Str::ulid();
            }
        });

        // Only a device keeps a mutation log. On the server these hooks do
        // nothing, so nothing about how it already behaves changes.
        //
        // Tether ships its own Syncable trait that logs unconditionally,
        // which can't work here: the same codebase is both the server and
        // the client, and a trait can't be applied to one and not the
        // other. Driving MutationLogger directly is what makes the single
        // config switch possible.
        static::created(function ($model): void {
            if (syncClientMode()) {
                app(MutationLogger::class)->recordCreate(
                    $model,
                    $model->tetherLoggableFields()
                );
            }
        });

        static::updated(function ($model): void {
            if (syncClientMode()) {
                app(MutationLogger::class)->recordUpdate(
                    $model,
                    $model->tetherLoggableFields()
                );
            }
        });

        static::deleted(function ($model): void {
            if (syncClientMode()) {
                app(MutationLogger::class)->recordDelete($model);
            }
        });
    }

    /**
     * The column holding this model's sync identity.
     *
     * MutationLogger checks for these methods rather than for Tether's own
     * trait, which is what lets this trait stand in for it.
     */
    public function getTetherKeyName(): string
    {
        return (string) config('tether-client.sync_key', 'tether_id');
    }

    public function getTetherKey(): string
    {
        return (string) $this->{$this->getTetherKeyName()};
    }

    /**
     * MutationLogger needs a concrete field list; getSyncableFields() may
     * return null to mean "no filtering", which for logging purposes means
     * everything the model considers fillable.
     *
     * @return string[]
     */
    public function tetherLoggableFields(): array
    {
        return $this->getSyncableFields() ?? $this->getFillable();
    }

    /**
     * Fields an incoming mutation payload is filtered to before being
     * written.
     *
     * Null means "no filtering beyond $fillable".
     *
     * @return string[]|null
     */
    public function getSyncableFields(): ?array
    {
        return isset($this->tetherSyncable)
            ? $this->tetherSyncable
            : null;
    }
}
