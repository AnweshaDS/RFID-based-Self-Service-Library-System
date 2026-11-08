<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['name', 'value'];

    /** A saved setting, or $default when none is saved (or the table is not there yet). */
    public static function read(string $name, ?string $default = null): ?string
    {
        try {
            $row = static::query()->where('name', $name)->first();
        } catch (\Throwable $e) {
            return $default;
        }

        return $row?->value ?? $default;
    }

    public static function write(string $name, string $value): void
    {
        static::query()->updateOrCreate(['name' => $name], ['value' => $value]);
    }
}