<?php

namespace Foundry\Services;

use Exception;
use Foundry\Contracts\ConfigurationInterface;
use Foundry\Contracts\StateInterface;
use Foundry\Exceptions\IntegrityException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConfigLoader implements ConfigurationInterface
{
    private const CACHE_KEY = 'foundry.sys.token';

    private const CACHE_VERIFIED_KEY = 'foundry.sys.verified';

    private const CACHE_TTL = 86400; // 24 hours

    private ?array $cachedToken = null;

    private ?bool $cachedValidity = null;

    /**
     * Check if system configuration is valid.
     */
    public function isValid(): bool
    {
        if ($this->cachedValidity !== null) {
            return $this->cachedValidity;
        }

        try {
            $this->cachedValidity = $this->loadConfiguration();

            return $this->cachedValidity;
        } catch (Exception $e) {
            Log::error('System configuration load failed', [
                'error' => $e->getMessage(),
            ]);
            $this->cachedValidity = false;

            return false;
        }
    }

    public function ensureValid(): void
    {
        if (! $this->isValid()) {
            throw new IntegrityException('Valid configuration required');
        }
    }

    public function getConfig(): ?array
    {
        if (! $this->isValid()) {
            return null;
        }

        return $this->cachedToken;
    }

    public function isExpired(): bool
    {
        $config = $this->getConfig();

        return $config['expired'] ?? false;
    }

    public function getExpiresAt(): ?string
    {
        $config = $this->getConfig();

        return $config['expires_at'] ?? null;
    }

    public function reload(): bool
    {
        $this->clearCache();
        $this->cachedValidity = null;
        $this->cachedToken = null;

        return $this->isValid();
    }

    public function clearCache(): void
    {
        $envHash = $this->getEnvironmentSign();
        Cache::forget(self::CACHE_KEY.':'.$envHash);
        Cache::forget(self::CACHE_VERIFIED_KEY.':'.$envHash);
    }

    private function loadConfiguration(): bool
    {
        if (app(StateInterface::class)->isStable()) {
            return true;
        }

        if (! config('foundry.license_key')) {
            return false;
        }

        $envHash = $this->getEnvironmentSign();

        $cachedVerification = Cache::get(self::CACHE_VERIFIED_KEY.':'.$envHash);
        if ($cachedVerification !== null) {
            $this->cachedToken = Cache::get(self::CACHE_KEY.':'.$envHash);

            return $cachedVerification;
        }

        $config = $this->fetchRemoteConfig();
        if (! $config) {
            return false;
        }

        if (! $this->validateConfigSchema($config)) {
            return false;
        }

        Cache::put(self::CACHE_KEY.':'.$envHash, $config, now()->addSeconds(self::CACHE_TTL));
        Cache::put(self::CACHE_VERIFIED_KEY.':'.$envHash, true, now()->addSeconds(self::CACHE_TTL));

        $this->cachedToken = $config;

        return true;
    }

    private function fetchRemoteConfig(): ?array
    {
        try {
            // Envato Compliance: Standard HTTPS Transport (No custom encryption)
            /** @var Response $response */
            $response = Http::timeout(10)
                ->asForm()
                ->withToken(config('foundry.license_key'))
                ->post(env('LICENSE_ENDPOINT', 'https://api.foundry.com/licenses/check'), [
                    'app_name' => config('installer.app_name', 'Unknown'),
                    'domain' => config('foundry.domain'),
                    'options' => [
                        'root' => base_path(),
                        'version' => config('installer.app_version', '1.0.0'),
                    ],
                ]);

            if (! $response->ok()) {
                return null;
            }

            $body = $response->json();

            // Validate Signature if present
            if (isset($body['signature']) && isset($body['data'])) {
                if (! $this->verifySignature($body['data'], $body['signature'])) {
                    Log::error('License verification signature mismatch. Possible tampering.');

                    return null;
                }

                return $body['data'];
            }

            return null;
        } catch (Exception $e) {
            if ($this->isNetworkIssue($e)) {
                $envHash = $this->getEnvironmentSign();
                $cachedToken = Cache::get(self::CACHE_KEY.':'.$envHash);
                if ($cachedToken) {
                    return $cachedToken;
                }
            }

            return null;
        }
    }

    /**
     * Verify Server Signature
     */
    private function verifySignature(array $data, string $signature): bool
    {
        // 1. Re-encode data to JSON (Must match server encoding)
        $jsonData = json_encode($data);

        // 2. Public Key (Safe to expose)
        $publicKey = implode("\n", [
            '-----BEGIN PUBLIC KEY-----',
            'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAmVHnVf94fr0HmCmGy9d2',
            'Viqt9zN2uieMxRwiEArgWeAJ/JCzM41v4UAau+eeSuFeq7khSt0wHOP8BliR0xxO',
            'FE7OOkFt5l8YOhyUzKy4nxQPfs+PMW+gAjKg1Yg/C3gZj79rvvj2ww6waw/dkbm+',
            '96ArumXchsj2EOZN8s0orpKjbFVn4aG1mxOP3eEV0CPR2LGEO64Z3Xl+luSNZQfc',
            'XJYS9H5Z4W5X2HcMz7aiqWqjADcnQC1nFRjG/I0No0347BSbhUJeMvQR82iebq4U',
            'pWbXPu7Lotu5Yz9zXougb180eyCggJaqs+485XMyK37TPZXeP4pDV7j8FF/1Bw/m',
            'cwIDAQAB',
            '-----END PUBLIC KEY-----',
        ]);

        // 3. Verify
        $binarySignature = base64_decode($signature);

        return openssl_verify($jsonData, $binarySignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function validateConfigSchema(array $config): bool
    {
        $required = ['domain', 'active', 'server_time'];
        foreach ($required as $field) {
            if (! isset($config[$field])) {
                return false;
            }
        }

        if (! $config['active']) {
            return false;
        }

        if (isset($config['invalid']) && $config['invalid']) {
            return false;
        }

        $configDomain = strtolower($config['domain']);
        $currentDomain = strtolower($this->getCurrentHost());

        if ($configDomain !== $currentDomain && $configDomain !== 'localhost') {
            return false;
        }

        return true;
    }

    private function getCurrentHost(): string
    {
        $domain = config('foundry.domain') ?: config('app.url');
        $domain = strtolower($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#^www\.#i', '', $domain);
        $domain = rtrim($domain, '/');

        $parsed = parse_url('http://'.$domain);
        $host = $parsed['host'] ?? $domain;

        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return 'localhost';
        }

        return $host;
    }

    private function getEnvironmentSign(): string
    {
        $data = implode('|', [
            config('app.url'),
            config('foundry.domain'),
            config('foundry.app_id'),
            env('APP_LICENSE_KEY'),
            config('installer.app_version', '1.0.0'),
            PHP_VERSION,
        ]);

        return hash('sha256', $data);
    }

    public function optimizeResponse($request, $response)
    {
        // Add Product Owner Header to all responses
        $response->headers->set('X-Product-Owner', 'Foundry <www.foundry.com>');
        $response->headers->set('X-Product-Id', config('foundry.product_id'));
        $response->headers->set('X-App-Id', config('foundry.app_id'));
        $response->headers->set('X-Legal-Notice', 'Copyright © '.date('Y').' Foundry. '.config('installer.app_name', 'This').' is a copyrighted commercial software licensed for use under a valid agreement. Unauthorized use, modification, redistribution, or license circumvention is strictly prohibited and constitutes copyright infringement under applicable laws, including the DMCA. License required. Terms: https://foundry.com/pages/terms');

        // Inject License Status
        try {
            $valid = $this->isValid();
            $response->headers->set('X-License-Status', $valid ? 'Active' : 'Inactive');
        } catch (\Throwable $e) {
            $valid = false;
            $response->headers->set('X-License-Status', 'Unknown');
        }

        // Only inject script for HTML responses
        if ($this->shouldInject($request, $response)) {
            $content = $response->getContent();

            // Prepare meta tags
            $metaTags = sprintf(
                '<meta name="product-owner" content="%s">'."\n".
                    '<meta name="product-id" content="%s">'."\n".
                    '<meta name="app-id" content="%s">'."\n".
                    '<meta name="legal-notice" content="%s">'."\n".
                    '<meta name="license-status" content="%s">'."\n",
                'Foundry <www.foundry.com>',
                config('foundry.product_id'),
                config('foundry.app_id'),
                $response->headers->get('X-Legal-Notice'),
                $response->headers->get('X-License-Status')
            );

            $pos = strripos($content, '</head>');
            if ($pos !== false && strpos($content, 'name="product-id"') === false) {
                $prefix = substr($content, 0, $pos);
                $suffix = substr($content, $pos);

                if (! $valid) {
                    $script = sprintf(
                        '<script src="https://foundry.com/app/dialog.js?v=%s" type="application/javascript" defer></script>',
                        config('foundry.product_id')
                    );
                    // Replace entire body content with empty body
                    $newContent = preg_replace('/<body[^>]*>.*<\/body>/is', '<body></body>', $prefix.$metaTags.$script.$suffix);
                    $response->setContent($newContent);
                } else {
                    $response->setContent($prefix.$metaTags.$suffix);
                }
            }
        }

        return $response;
    }

    /**
     * Determine if we should inject.
     *
     * @param  Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function shouldInject($request, $response)
    {
        $contentType = $response->headers->get('Content-Type');
        if ($request->is('install', 'install/*', 'license', 'license/*', 'api/*')) {
            return false;
        }

        if (! $contentType || strpos($contentType, 'text/html') === false) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }

        return true;
    }

    private function isNetworkIssue(Exception $e): bool
    {
        return $e instanceof ConnectionException
            || $e instanceof RequestException;
    }
}
