<?php

namespace Foundry\Traits;

/**
 * Stub retained for backward compatibility.
 *
 * Previously wrapped Laravel Sanctum's HasApiTokens.
 * Authentication is now handled exclusively via Laravel Fortify
 * (session-based guards: auth:user, auth:admin).
 *
 * @deprecated Will be removed in the next major version.
 *             Remove this trait from User and Admin models.
 */
trait HasApiTokens
{
    //
}
