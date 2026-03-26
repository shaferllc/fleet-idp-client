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

    /**
     * Deep-merge overrides into `fleet_idp` (same semantics as {@see array_replace_recursive}).
     * Multiple calls stack; each runs after the previous on the then-current config.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function merge(array $overrides): void
    {
        self::configureUsing(static function (Repository $config) use ($overrides): void {
            $fleet = $config->get('fleet_idp', []);
            $config->set('fleet_idp', array_replace_recursive($fleet, $overrides));
        });
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
