<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Stored challenges for satellite-local email code / magic link (non–Fleet-managed users).
 *
 * @property string $id
 * @property string $email
 * @property string|null $code_hash
 * @property string|null $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
class LocalEmailLoginChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'code_hash',
        'token_hash',
        'expires_at',
        'consumed_at',
    ];

    public function getTable(): string
    {
        return (string) config('fleet_idp.email_sign_in.challenges_table', 'local_email_login_challenges');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LocalEmailLoginChallenge $model): void {
            if ($model->id === null || $model->id === '') {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
