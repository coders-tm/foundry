<?php

namespace Foundry\Billable\Responses;

class RedirectResponse
{
    /**
     * The URL to redirect to.
     */
    protected string $url;

    /**
     * Additional data associated with the redirect.
     */
    protected array $data = [];

    /**
     * Create a new redirect response instance.
     */
    public function __construct(string $url, array $data = [])
    {
        $this->url = $url;
        $this->data = $data;
    }

    /**
     * Get the URL to redirect to.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get additional data associated with the redirect.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get a specific data item.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Determine if this is a redirect response.
     */
    public function isRedirect(): bool
    {
        return true;
    }
}
