<?php

namespace Foundry\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface ConfigurationInterface
{
    /**
     * Check if the configuration is valid.
     *
     * @return bool
     */
    public function isValid();

    /**
     * Optimize the response with custom headers and script injections.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return Response
     */
    public function optimizeResponse($request, $response);
}
