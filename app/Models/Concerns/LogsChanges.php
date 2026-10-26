<?php

namespace App\Models\Concerns;

use Illuminate\Support\Arr;

/**
 * @method static void created(\Closure|string $callback)
 * @method static void updating(\Closure|string $callback)
 * @method static void deleting(\Closure|string $callback)
 */
trait LogsChanges
{

    public static function bootLogsChanges(): void
    {
        static::created(function ($model) {
            static::stashAudit($model, 'created', []);
        });

        static::updating(function ($model) {
            $skip = array_merge($model->getHidden(), ['password', 'remember_token']);
            $changes = [];

            foreach ($model->getDirty() as $field => $new) {
                if (in_array($field, $skip, true) || $field === 'updated_at') {
                    continue;
                }
                $changes[$field] = [
                    'old' => $model->getRawOriginal($field),
                    'new' => $new,
                ];
            }

            if ($changes) {
                static::stashAudit($model, 'updated', $changes);
            }
        });

        static::deleting(function ($model) {
            $skip = array_merge($model->getHidden(), ['password', 'remember_token']);
            static::stashAudit($model, 'deleted', Arr::except($model->getRawOriginal(), $skip));
        });
    }

    protected static function stashAudit($model, string $event, array $data): void
    {
        try {
            if (app()->runningInConsole()) {
                return;
            }

            $request = request();
            $list = $request->attributes->get('audit_changes', []);

            $list[] = [
                'model' => class_basename($model),
                'id'    => $model->getKey(),
                'event' => $event,
                'data'  => $data,
            ];

            $request->attributes->set('audit_changes', $list);
        } catch (\Throwable $e) {
            // never break saving a record because of logging
        }
    }
}
