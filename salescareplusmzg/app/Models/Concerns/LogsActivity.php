<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

/**
 * Records a row in activity_logs for every create/update/delete made by a
 * logged-in admin (console/seeder writes have no authenticated user, so
 * they're silently skipped — the log only ever shows real admin actions).
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->recordActivity('created'));

        static::updated(function ($model) {
            if ($model->wasChanged()) {
                $model->recordActivity('updated');
            }
        });

        static::deleted(fn ($model) => $model->recordActivity('deleted'));
    }

    protected function recordActivity(string $action): void
    {
        if (! auth()->check()) {
            return;
        }

        $changes = null;

        if ($action === 'updated') {
            $changes = collect($this->getChanges())
                ->except(['password', 'remember_token', 'updated_at'])
                ->map(function ($value) {
                    $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value);

                    return Str::limit($value, 100);
                })
                ->toArray();

            if (empty($changes)) {
                return;
            }
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'action' => $action,
            'subject_type' => class_basename($this),
            'subject_id' => $this->getKey(),
            'subject_label' => $this->activityLabel(),
            'changes' => $changes,
        ]);
    }

    protected function activityLabel(): string
    {
        foreach (['name', 'title', 'question', 'key', 'label'] as $field) {
            if (! empty($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }
}
