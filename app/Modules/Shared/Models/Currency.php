<?php

namespace App\Modules\Shared\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'minor_units', 'is_active'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minor_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Convert a major-unit value (e.g. 12.34) to integer minor units.
     */
    public static function toMinorUnits(int|float|string $major, int $minorUnits): int
    {
        return (int) round((float) $major * (10 ** $minorUnits));
    }
}
