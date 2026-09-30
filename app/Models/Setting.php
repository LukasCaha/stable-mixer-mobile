<?php

namespace App\Models;

use App\Support\TenantCode;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function tenant(): ?string
    {
        $value = static::query()->where('key', 'tenant_code')->value('value');

        return is_string($value) && TenantCode::isValid($value) ? $value : null;
    }

    public static function putTenant(string $code): void
    {
        static::query()->updateOrCreate(
            ['key' => 'tenant_code'],
            ['value' => $code],
        );
    }

    public static function stableName(): ?string
    {
        $value = static::query()->where('key', 'stable_name')->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function putStable(string $code, string $name): void
    {
        static::putTenant($code);
        static::query()->updateOrCreate(
            ['key' => 'stable_name'],
            ['value' => $name],
        );
    }
}
