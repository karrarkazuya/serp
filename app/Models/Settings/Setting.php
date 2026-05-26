<?php

namespace App\Models\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use SoftDeletes;

    // Rule 4: uuid / created_by / updated_by are observer-managed, never in $fillable.
    protected $fillable = ['key', 'value', 'group', 'type', 'label', 'description'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'float'   => (float) $setting->value,
            'json'    => json_decode($setting->value, true),
            default   => $setting->value,
        };
    }

    public static function setValue(string $key, mixed $value): static
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored]
        );
    }

    public static function getGroup(string $group): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('group', $group)->get();
    }
}
