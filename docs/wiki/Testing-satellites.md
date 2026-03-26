# Testing satellites

## Password reset / forgot flows

Use the package test trait so Feature tests stay short and consistent:

```php
use Fleet\IdpClient\Testing\InteractsWithFleetIdpPasswordReset;

class PasswordResetTest extends TestCase
{
    use InteractsWithFleetIdpPasswordReset;

    public function test_something(): void
    {
        $this->configureFleetIdpWithProvisioningLookup(
            'https://fleet.example.test',
            'provision-secret',
        );
        $this->fakeFleetProvisioningUserLookup(true);

        // POST /forgot-password ...
    }
}
```

- **`configureFleetIdpWithProvisioningLookup()`** sets `fleet_idp.url`, disables `local_password_only`, sets provisioning token.
- **`fakeFleetProvisioningUserLookup(bool $exists)`** fakes **`POST .../api/provisioning/users/lookup`**.
- **`fakeFleetProvisioningPasswordReset(bool $success = true)`** fakes **`POST .../api/provisioning/users/password-reset`** (HTTP 200 vs 500).

Use **`Http::fake()`** with multiple URL keys when a flow hits both lookup and password-reset. Use **`Http::assertSent()`** to assert Bearer and JSON body.

## PHPUnit environment

In **`phpunit.xml`**, common patterns:

- Clear **`FLEET_IDP_URL`** and set **`FLEET_IDP_LOCAL_PASSWORD_ONLY=true`** when tests must exercise **local** broker only (no Fleet redirect/lookup).
- Or configure Fleet URL + **`Http::fake`** for lookup, as above.

## `FleetIdp` in application code

`Fleet\IdpClient\FleetIdp` is the supported façade-style entry for:

- `passwordManagedByIdp($user)`
- `idpChangePasswordUrl()`, `changePasswordRouteName()`
- `emailExistsOnFleet($email)` (usually internal; controller uses `FleetProvisioningUserLookup` directly)

Unit tests can **`Config::set`** and **`Http::fake`** without hitting the network.

## Related

- [Account and password](Account-and-password)
- [Provisioning and Fleet lookup](Provisioning-and-Fleet-lookup)
