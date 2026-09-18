<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $log_name
 * @property string|null $name
 * @property string|null $tcgdex_id
 * @property string|null $image
 * @property string|null $category
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Card extends Model
{
    protected $guarded = [];

    /**
     * Normalize a raw card name from a battle log into the stable lookup key.
     */
    public static function normalizeName(string $name): string
    {
        $name = Str::ascii(trim($name));

        return Str::lower(str_replace("'", '', $name));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
