<?php

declare(strict_types=1);

namespace Fleet\IdpClient;

use RuntimeException;

/**
 * Thrown when FLEET_IDP_REDIRECT_URI is malformed (e.g. comma-separated list).
 */
class InvalidRedirectUriConfig extends RuntimeException
{
}
