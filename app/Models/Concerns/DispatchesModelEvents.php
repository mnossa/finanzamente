<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Events\ModelChanged;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait DispatchesModelEvents
 *
 * Questo trait è un "Concern" riutilizzabile per i modelli Eloquent. In progetti
 * Laravel è buona pratica estrarre comportamenti condivisi dei modelli in Traits
 * sotto `App\Models\Concerns` per mantenere i modelli snelli e componibili.
 *
 * Scopo
 * - Invia automaticamente un evento generico `ModelChanged` quando il modello
 *   viene creato, aggiornato o eliminato.
 * - Fornisce un unico punto di aggancio coerente in cui i listener possono
 *   reagire ai cambiamenti del ciclo di vita del modello senza accoppiare
 *   il codice a molteplici eventi specifici.
 *
 * Uso
 * - Usare `use DispatchesModelEvents;` all'interno di qualsiasi classe modello.
 * - Registrare i listener per `App\Events\ModelChanged` in
 *   `App\Providers\EventServiceProvider`.
 *
 * Note e compromessi
 * - Il trait dispatcha direttamente l'istanza del modello. Se prevedi di
 *   mettere in coda listener o inviare eventi a sistemi esterni, valuta di
 *   inviare un payload più leggero (id + class + action) o rendere l'evento
 *   queueable implementando `ShouldQueue`.
 * - Questo trait emette un evento generico. Per logiche di dominio complesse
 *   potresti preferire eventi specifici (es. `TransactionCreated`) per esprimere
 *   l'intento in modo più chiaro.
 *
 * Esempio
 * class Transaction extends Model
 * {
 *     use \App\Models\Concerns\DispatchesModelEvents;
 * }
 */
trait DispatchesModelEvents
{
    /**
     * Boot the trait and register model lifecycle listeners.
     *
     * Laravel will call a static `boot{TraitName}` method automatically when
     * the model boots. Here we listen to created/updated/deleted and dispatch
     * a generic `ModelChanged` event with the model instance and the action
     * name.
     *
     * Keeping this logic in a trait keeps controllers/services free of side
     * effects and centralises cross-cutting concerns.
     */
    public static function bootDispatchesModelEvents(): void
    {
        static::created(function (Model $model) {
            // Dispatch a small, generic domain event indicating the model was created.
            event(new ModelChanged($model, 'created'));
        });

        static::updated(function (Model $model) {
            // When a model is updated, listeners can inspect $model->getChanges()
            // to decide what to do (e.g. recalculate aggregates only when
            // specific attributes changed).
            event(new ModelChanged($model, 'updated'));
        });

        static::deleted(function (Model $model) {
            // Note: soft-deletes will also trigger `deleted` events. If you
            // need a distinction between hard/soft delete, check the model's
            // `usesSoftDeletes()` or `isForceDeleting()` inside listeners.
            event(new ModelChanged($model, 'deleted'));
        });
    }
}
