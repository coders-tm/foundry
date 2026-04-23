<?php

namespace Foundry\Traits;

use Illuminate\Auth\Access\HandlesAuthorization as AccessHandlesAuthorization;
use Illuminate\Auth\Access\Response;

trait HandlesAuthorization
{
    use AccessHandlesAuthorization;

    /**
     * Throws an unauthorized exception.
     *
     * @param  string|null  $message
     * @param  mixed|null  $code
     * @return Response
     */
    protected function deny($message = null, $code = null)
    {
        return Response::deny($message ?? 'You do not have permission to access. Please contact your administrator to request access.', $code);
    }
}
