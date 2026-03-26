<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Services;

use Fleet\IdpClient\Models\LocalEmailLoginChallenge;
use Fleet\IdpClient\Support\EmailSignInUserOptions;
use Fleet\IdpClient\Notifications\LocalEmailLoginCodeNotification;
use Fleet\IdpClient\Notifications\LocalEmailLoginMagicLinkNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Local (satellite-only) email code and magic link delivery and verification.
 */
final class LocalEmailLoginService
{
    private function codeTtlMinutes(): int
    {
        $minutes = (int) config('fleet_idp.email_sign_in.local_code_ttl_minutes', 0);
        if ($minutes >= 1) {
            return $minutes;
        }

        return max(1, (int) config('auth.passwordless.code_ttl_minutes', 10));
    }

    private function magicTtlMinutes(): int
    {
        $minutes = (int) config('fleet_idp.email_sign_in.local_magic_link_ttl_minutes', 0);
        if ($minutes >= 1) {
            return $minutes;
        }

        return max(1, (int) config('auth.passwordless.magic_link_ttl_minutes', 30));
    }

    public function magicLinkReturnUrl(): string
    {
        $name = (string) config('fleet_idp.email_sign_in.route_names.magic_login', 'login.email-magic');
        if (Route::has($name)) {
            return route($name);
        }

        $path = ltrim((string) config('fleet_idp.email_sign_in.paths.magic_login', 'login/email-magic'), '/');

        return url($path);
    }

    /**
     * @param  class-string<Model>  $userClass
     */
    private function userClass(): string
    {
        /** @var class-string<Model> $class */
        $class = (string) config('fleet_idp.user_model', 'App\\Models\\User');

        return $class;
    }

    public function sendCode(Model $user): bool
    {
        if (! EmailSignInUserOptions::userAllowsCode($user)) {
            return false;
        }

        $email = Str::lower((string) $user->email);
        $plain = (string) random_int(100000, 999999);
        $expiresMinutes = $this->codeTtlMinutes();

        LocalEmailLoginChallenge::query()->updateOrCreate(
            ['email' => $email],
            [
                'code_hash' => hash('sha256', $plain),
                'token_hash' => null,
                'expires_at' => now()->addMinutes($expiresMinutes),
                'consumed_at' => null,
            ]
        );

        $user->notify(new LocalEmailLoginCodeNotification($plain, $expiresMinutes));

        return true;
    }

    public function sendMagicLink(Model $user): bool
    {
        if (! EmailSignInUserOptions::userAllowsMagicLink($user)) {
            return false;
        }

        $email = Str::lower((string) $user->email);
        $plainToken = Str::random(64);
        $expiresMinutes = $this->magicTtlMinutes();

        LocalEmailLoginChallenge::query()->updateOrCreate(
            ['email' => $email],
            [
                'code_hash' => null,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes($expiresMinutes),
                'consumed_at' => null,
            ]
        );

        $sep = str_contains($this->magicLinkReturnUrl(), '?') ? '&' : '?';
        $magicUrl = $this->magicLinkReturnUrl().$sep.'token='.rawurlencode($plainToken);

        $user->notify(new LocalEmailLoginMagicLinkNotification($magicUrl, $expiresMinutes));

        return true;
    }

    public function verifyCode(string $email, string $code): ?Model
    {
        $email = Str::lower(trim($email));
        $userClass = $this->userClass();
        /** @var Model|null $user */
        $user = $userClass::query()->where('email', $email)->first();
        if ($user === null || ! EmailSignInUserOptions::userAllowsCode($user)) {
            return null;
        }

        $challenge = LocalEmailLoginChallenge::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($challenge === null
            || ! is_string($challenge->code_hash)
            || ! hash_equals($challenge->code_hash, hash('sha256', trim($code)))) {
            return null;
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        return $user;
    }

    public function verifyMagicToken(string $token): ?Model
    {
        $tHash = hash('sha256', trim($token));
        $challenge = LocalEmailLoginChallenge::query()
            ->where('token_hash', $tHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($challenge === null) {
            return null;
        }

        $userClass = $this->userClass();
        /** @var Model|null $user */
        $user = $userClass::query()->where('email', $challenge->email)->first();
        if ($user === null || ! EmailSignInUserOptions::userAllowsMagicLink($user)) {
            return null;
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        return $user;
    }
}
