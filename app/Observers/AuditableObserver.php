<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditableObserver
{
    private const SYSTEM_USER_ID = 0;

    /** @var array<string, array<string, bool>> */
    private static array $columns = [];

    public function creating(Model $model): void
    {
        if ($this->hasColumn($model, 'uuid') && empty($model->getAttribute('uuid'))) {
            $model->forceFill(['uuid' => (string) Str::uuid()]);
        }

        $userId = Auth::id() ?? self::SYSTEM_USER_ID;

        if ($this->hasColumn($model, 'created_by') && $model->getAttribute('created_by') === null) {
            $model->forceFill(['created_by' => $userId]);
        }

        if ($this->hasColumn($model, 'updated_by') && $model->getAttribute('updated_by') === null) {
            $model->forceFill(['updated_by' => $userId]);
        }
    }

    public function updating(Model $model): void
    {
        $userId = Auth::id() ?? self::SYSTEM_USER_ID;

        if ($this->hasColumn($model, 'updated_by')) {
            $model->forceFill(['updated_by' => $userId]);
        }
    }

    public function updated(Model $model): void
    {
        if (empty($model->chatterTracked) || !method_exists($model, 'logMessage')) {
            return;
        }

        $changes = [];

        foreach ($model->chatterTracked as $column => $label) {
            if (!$model->wasChanged($column)) {
                continue;
            }
            $changes[] = [
                'label' => $label,
                'from'  => (string) ($model->getOriginal($column) ?? ''),
                'to'    => (string) ($model->getAttribute($column) ?? ''),
            ];
        }

        if (empty($changes)) {
            return;
        }

        $model->logMessage('Record updated.', 'log', ['changes' => $changes]);
    }

    private function hasColumn(Model $model, string $column): bool
    {
        $table = $model->getTable();

        if (!array_key_exists($table, self::$columns)) {
            self::$columns[$table] = [];
        }

        if (!array_key_exists($column, self::$columns[$table])) {
            self::$columns[$table][$column] = Schema::hasColumn($table, $column);
        }

        return self::$columns[$table][$column];
    }
}
