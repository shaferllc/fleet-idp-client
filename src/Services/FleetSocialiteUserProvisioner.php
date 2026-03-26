<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

/**
 * Finds or creates a local user from a Socialite user (GitHub / Google).
 * Expects the user model to use string columns provider, provider_id, email, name, password.
 */
final class FleetSocialiteUserProvisioner
{
    public static function resolve(string $provider, SocialiteUser $social): Authenticatable&Model
    {
        $provider = Str::lower($provider);

        /** @var class-string<Model&Authenticatable> $class */
        $class = config('fleet_idp.socialite.user_model') ?? config('fleet_idp.user_model');
        if (! class_exists($class)) {
            throw new RuntimeException('fleet_idp.socialite.user_model / fleet_idp.user_model is not a valid class.');
        }

        $model = new $class;
        if (! $model instanceof Authenticatable) {
            throw new RuntimeException($class.' must implement Authenticatable.');
        }

        $email = $social->getEmail();
        if (! is_string($email) || trim($email) === '') {
            throw new RuntimeException('missing_email');
        }

        $email = Str::lower(trim($email));

        /** @var Model&Authenticatable $found */
        $found = $class::query()
            ->where('provider', $provider)
            ->where('provider_id', (string) $social->getId())
            ->first();

        if ($found !== null) {
            return $found;
        }

        /** @var (Model&Authenticatable)|null $existing */
        $existing = $class::query()->where('email', $email)->first();

        if ($existing !== null) {
            $existingProvider = $existing->getAttribute('provider');
            if ($existingProvider !== null && (string) $existingProvider !== '' && (string) $existingProvider !== $provider) {
                throw new RuntimeException('email_provider_conflict');
            }

            $name = $social->getName();
            $displayName = is_string($name) && $name !== '' ? $name : Str::before($email, '@');

            $existing->forceFill([
                'provider' => $provider,
                'provider_id' => (string) $social->getId(),
                'email_verified_at' => $existing->getAttribute('email_verified_at') ?? now(),
                'name' => $existing->getAttribute('name') ?: $displayName,
            ])->save();

            return $existing;
        }

        $name = $social->getName();
        $displayName = is_string($name) && $name !== '' ? $name : Str::before($email, '@');

        $password = (bool) config('fleet_idp.socialite.null_password_for_social', true)
            ? null
            : Hash::make(Str::random(32));

        /** @var Model&Authenticatable $created */
        $created = $class::query()->create([
            'name' => $displayName,
            'email' => $email,
            'password' => $password,
            'provider' => $provider,
            'provider_id' => (string) $social->getId(),
            'email_verified_at' => now(),
        ]);

        return $created;
    }
}
