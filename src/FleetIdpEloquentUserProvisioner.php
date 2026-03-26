<?php

namespace Fleet\IdpClient;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class FleetIdpEloquentUserProvisioner
{
    /**
     * @param  array{id: int|string, name?: string|null, email?: string|null}  $remote
     * @return array{user: ?Model, error: ?string}
     */
    public static function syncFromRemoteUser(array $remote): array
    {
        $modelClass = self::resolveUserModelClass();
        $provider = (string) config('fleet_idp.provider_name');

        $email = $remote['email'] ?? null;
        if (! is_string($email) || $email === '') {
            return ['user' => null, 'error' => trans('fleet-idp::errors.no_email')];
        }

        $email = Str::lower($email);
        $providerId = (string) $remote['id'];

        /** @var Model|null $user */
        $user = $modelClass::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($user) {
            if (isset($remote['name']) && is_string($remote['name']) && $remote['name'] !== '') {
                $user->forceFill(['name' => $remote['name']])->save();
            }

            return ['user' => $user, 'error' => null];
        }

        /** @var Model|null $existing */
        $existing = $modelClass::query()->where('email', $email)->first();

        if ($existing) {
            if ($existing->getAttribute('provider') !== null && $existing->getAttribute('provider') !== $provider) {
                return ['user' => null, 'error' => trans('fleet-idp::errors.provider_conflict')];
            }

            $existing->forceFill([
                'provider' => $provider,
                'provider_id' => $providerId,
                'email_verified_at' => $existing->getAttribute('email_verified_at') ?? now(),
                'name' => $existing->getAttribute('name') ?: ($remote['name'] ?? Str::before($email, '@')),
            ])->save();

            return ['user' => $existing, 'error' => null];
        }

        $name = is_string($remote['name'] ?? null) && $remote['name'] !== ''
            ? $remote['name']
            : Str::before($email, '@');

        /** @var Model $user */
        $user = $modelClass::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => null,
            'provider' => $provider,
            'provider_id' => $providerId,
            'email_verified_at' => now(),
        ]);

        return ['user' => $user, 'error' => null];
    }

    /**
     * @return class-string<Model>
     */
    private static function resolveUserModelClass(): string
    {
        $class = config('fleet_idp.user_model');
        if (! is_string($class) || $class === '') {
            throw new RuntimeException('fleet_idp.user_model is not configured.');
        }

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException('fleet_idp.user_model must be an Eloquent model class: '.$class);
        }

        return $class;
    }
}
