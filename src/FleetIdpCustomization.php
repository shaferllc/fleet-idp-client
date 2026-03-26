<?php

declare(strict_types=1);

namespace Fleet\IdpClient;

use Illuminate\Contracts\Config\Repository;

/**
 * Adjust `fleet_idp` from PHP without publishing `config/fleet_idp.php`.
 *
 * Register callbacks from {@see \Illuminate\Support\ServiceProvider::register()} (not `boot()`):
 * they run at the very start of {@see FleetIdpServiceProvider::boot()}, before redirect
 * normalization and route registration.
 */
final class FleetIdpCustomization
{
    /** @var list<callable(Repository): void> */
    private static array $configureUsing = [];

    public static function configureUsing(callable $callback): void
    {
        self::$configureUsing[] = $callback;
    }

    public static function apply(Repository $config): void
    {
        foreach (self::$configureUsing as $callback) {
            $callback($config);
        }
    }

    /**
     * @internal
     */
    public static function flush(): void
    {
        self::$configureUsing = [];
    }
}
