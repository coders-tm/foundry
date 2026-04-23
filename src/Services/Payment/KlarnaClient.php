<?php

namespace Foundry\Services\Payment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Config;

class KlarnaClient
{
    protected $client;

    protected $username;

    protected $password;

    protected $baseUrl;

    public function __construct($options = [])
    {
        // Prefer explicit options, then config, then fail
        $this->username = $options['username'] ?? Config::get('klarna.api_key');
        $this->password = $options['password'] ?? Config::get('klarna.api_secret');
        $testMode = $options['test_mode'] ?? Config::get('klarna.test_mode', false);
        $this->baseUrl = rtrim(
            $options['base_url'] ?? ($testMode ? 'https://api.playground.klarna.com' : 'https://api.klarna.com'),
            '/'
        );

        if (empty($this->username) || empty($this->password)) {
            throw new \InvalidArgumentException('Klarna API credentials are required.');
        }

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'auth' => [$this->username, $this->password],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Create a new payment session
     *
     * @param  array  $sessionData  Session data
     * @return array Response from Klarna API
     *
     * @throws \Exception
     */
    public function createSession(array $sessionData)
    {
        try {
            $response = $this->client->post('/payments/v1/sessions', [
                'json' => $sessionData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $msg = $e->getMessage();
            if ($e->hasResponse()) {
                $msg .= ' Response: '.$e->getResponse()->getBody()->getContents();
            }
            throw new \Exception('Failed to create Klarna session: '.$msg, $e->getCode());
        }
    }

    /**
     * Update an existing payment session
     *
     * @param  string  $sessionId  Session ID
     * @param  array  $sessionData  Updated session data
     * @return void
     *
     * @throws \Exception
     */
    public function updateSession(string $sessionId, array $sessionData)
    {
        try {
            $this->client->post("/payments/v1/sessions/{$sessionId}", [
                'json' => $sessionData,
            ]);
        } catch (RequestException $e) {
            $msg = $e->getMessage();
            if ($e->hasResponse()) {
                $msg .= ' Response: '.$e->getResponse()->getBody()->getContents();
            }
            throw new \Exception('Failed to update Klarna session: '.$msg, $e->getCode());
        }
    }

    /**
     * Get details about a payment session
     *
     * @param  string  $sessionId  Session ID
     * @return array Session details
     *
     * @throws \Exception
     */
    public function getSession(string $sessionId)
    {
        try {
            $response = $this->client->get("/payments/v1/sessions/{$sessionId}");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $msg = $e->getMessage();
            if ($e->hasResponse()) {
                $msg .= ' Response: '.$e->getResponse()->getBody()->getContents();
            }
            throw new \Exception('Failed to get Klarna session: '.$msg, $e->getCode());
        }
    }

    /**
     * Create an order from an authorization token
     *
     * @param  string  $authorizationToken  Authorization token
     * @param  array  $orderData  Order data
     * @return array Order response
     *
     * @throws \Exception
     */
    public function createOrder(string $authorizationToken, array $orderData)
    {
        try {
            $response = $this->client->post("/payments/v1/authorizations/{$authorizationToken}/order", [
                'json' => $orderData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $msg = $e->getMessage();
            if ($e->hasResponse()) {
                $msg .= ' Response: '.$e->getResponse()->getBody()->getContents();
            }
            throw new \Exception('Failed to create Klarna order: '.$msg, $e->getCode());
        }
    }

    /**
     * Cancel an authorization
     *
     * @param  string  $authorizationToken  Authorization token
     * @return void
     *
     * @throws \Exception
     */
    public function cancelAuthorization(string $authorizationToken)
    {
        try {
            $this->client->delete("/payments/v1/authorizations/{$authorizationToken}");
        } catch (RequestException $e) {
            throw new \Exception('Failed to cancel Klarna authorization: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Generate a customer token
     *
     * @param  string  $authorizationToken  Authorization token
     * @param  array  $tokenData  Token data
     * @return array Token response
     *
     * @throws \Exception
     */
    public function generateCustomerToken(string $authorizationToken, array $tokenData)
    {
        try {
            $response = $this->client->post("/payments/v1/authorizations/{$authorizationToken}/customer-token", [
                'json' => $tokenData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to generate Klarna customer token: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Get order details from Order Management API
     *
     * @param  string  $orderId  Order ID
     * @return array Order details
     *
     * @throws \Exception
     */
    public function getOrder(string $orderId)
    {
        try {
            $response = $this->client->get("/ordermanagement/v1/orders/{$orderId}");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get Klarna order: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Acknowledge an order
     *
     * @param  string  $orderId  Order ID
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function acknowledgeOrder(string $orderId, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->post("/ordermanagement/v1/orders/{$orderId}/acknowledge", [
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to acknowledge Klarna order: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Update order authorization (amount and order lines)
     *
     * @param  string  $orderId  Order ID
     * @param  array  $updateData  Update data
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function updateAuthorization(string $orderId, array $updateData, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->patch("/ordermanagement/v1/orders/{$orderId}/authorization", [
                'json' => $updateData,
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to update Klarna order authorization: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Update customer details (shipping address)
     *
     * @param  string  $orderId  Order ID
     * @param  array  $customerData  Customer data
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function updateCustomerDetails(string $orderId, array $customerData, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->patch("/ordermanagement/v1/orders/{$orderId}/customer-details", [
                'json' => $customerData,
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to update Klarna customer details: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Extend authorization time
     *
     * @param  string  $orderId  Order ID
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function extendAuthorizationTime(string $orderId, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->post("/ordermanagement/v1/orders/{$orderId}/extend-authorization-time", [
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to extend Klarna authorization time: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Update merchant references
     *
     * @param  string  $orderId  Order ID
     * @param  array  $references  Merchant references
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function updateMerchantReferences(string $orderId, array $references, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->patch("/ordermanagement/v1/orders/{$orderId}/merchant-references", [
                'json' => $references,
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to update Klarna merchant references: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Release remaining authorization
     *
     * @param  string  $orderId  Order ID
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function releaseRemainingAuthorization(string $orderId, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->post("/ordermanagement/v1/orders/{$orderId}/release-remaining-authorization", [
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to release Klarna remaining authorization: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Add shipping information to an order
     *
     * @param  string  $orderId  Order ID
     * @param  array  $shippingInfo  Shipping information
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function addShippingInfo(string $orderId, array $shippingInfo, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->post("/ordermanagement/v1/orders/{$orderId}/shipping-info", [
                'json' => $shippingInfo,
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to add Klarna shipping info: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Cancel an order
     *
     * @param  string  $orderId  Order ID
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function cancelOrder(string $orderId, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->post("/ordermanagement/v1/orders/{$orderId}/cancel", [
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to cancel Klarna order: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Capture an order
     *
     * @param  string  $orderId  Order ID
     * @param  array  $captureData  Capture data
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return array Capture response
     *
     * @throws \Exception
     */
    public function captureOrder(string $orderId, array $captureData, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $response = $this->client->post("/ordermanagement/v1/orders/{$orderId}/captures", [
                'json' => $captureData,
                'headers' => $headers,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to capture Klarna order: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Get all captures for an order
     *
     * @param  string  $orderId  Order ID
     * @return array List of captures
     *
     * @throws \Exception
     */
    public function getCaptures(string $orderId)
    {
        try {
            $response = $this->client->get("/ordermanagement/v1/orders/{$orderId}/captures");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get Klarna captures: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Get capture details
     *
     * @param  string  $orderId  Order ID
     * @param  string  $captureId  Capture ID
     * @return array Capture details
     *
     * @throws \Exception
     */
    public function getCapture(string $orderId, string $captureId)
    {
        try {
            $response = $this->client->get("/ordermanagement/v1/orders/{$orderId}/captures/{$captureId}");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get Klarna capture: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Extend payment due date for a capture
     *
     * @param  string  $orderId  Order ID
     * @param  string  $captureId  Capture ID
     * @param  int  $numberOfDays  Number of days to extend
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function extendDueDate(string $orderId, string $captureId, int $numberOfDays, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->patch("/ordermanagement/v1/orders/{$orderId}/captures/{$captureId}/extend-due-date", [
                'json' => ['number_of_days' => $numberOfDays],
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to extend Klarna due date: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Get options for extending due date
     *
     * @param  string  $orderId  Order ID
     * @param  string  $captureId  Capture ID
     * @return array Extension options
     *
     * @throws \Exception
     */
    public function getExtendDueDateOptions(string $orderId, string $captureId)
    {
        try {
            $response = $this->client->get("/ordermanagement/v1/orders/{$orderId}/captures/{$captureId}/extend-due-date-options");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get Klarna extend due date options: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Add shipping information to a capture
     *
     * @param  string  $orderId  Order ID
     * @param  string  $captureId  Capture ID
     * @param  array  $shippingInfo  Shipping information
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function addCaptureShippingInfo(string $orderId, string $captureId, array $shippingInfo, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->post("/ordermanagement/v1/orders/{$orderId}/captures/{$captureId}/shipping-info", [
                'json' => $shippingInfo,
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to add Klarna capture shipping info: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Trigger customer communication send out
     *
     * @param  string  $orderId  Order ID
     * @param  string  $captureId  Capture ID
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return void
     *
     * @throws \Exception
     */
    public function triggerSendOut(string $orderId, string $captureId, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $this->client->post("/ordermanagement/v1/orders/{$orderId}/captures/{$captureId}/trigger-send-out", [
                'headers' => $headers,
            ]);
        } catch (RequestException $e) {
            throw new \Exception('Failed to trigger Klarna send out: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Refund an order
     *
     * @param  string  $orderId  Order ID
     * @param  array  $refundData  Refund data
     * @param  string|null  $idempotencyKey  Idempotency key for safe retries
     * @return array Refund response
     *
     * @throws \Exception
     */
    public function refundOrder(string $orderId, array $refundData, ?string $idempotencyKey = null)
    {
        try {
            $headers = [];
            if ($idempotencyKey) {
                $headers['Klarna-Idempotency-Key'] = $idempotencyKey;
            }

            $response = $this->client->post("/ordermanagement/v1/orders/{$orderId}/refunds", [
                'json' => $refundData,
                'headers' => $headers,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to refund Klarna order: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Get refund details
     *
     * @param  string  $orderId  Order ID
     * @param  string  $refundId  Refund ID
     * @return array Refund details
     *
     * @throws \Exception
     */
    public function getRefund(string $orderId, string $refundId)
    {
        try {
            $response = $this->client->get("/ordermanagement/v1/orders/{$orderId}/refunds/{$refundId}");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get Klarna refund: '.$e->getMessage(), $e->getCode());
        }
    }

    /**
     * Helper method to build session data
     *
     * @param  array  $params  Session parameters
     * @return array Formatted session data
     */
    public function buildSessionData(array $params)
    {
        $defaultData = [
            'acquiring_channel' => 'ECOMMERCE',
            'intent' => 'buy',
        ];

        // Required fields validation
        $required = ['order_amount', 'order_lines', 'purchase_country', 'purchase_currency'];
        foreach ($required as $field) {
            if (! isset($params[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing.");
            }
        }

        return array_merge($defaultData, $params);
    }

    /**
     * Helper method to build order data
     *
     * @param  array  $params  Order parameters
     * @return array Formatted order data
     */
    public function buildOrderData(array $params)
    {
        $defaultData = [
            'auto_capture' => false,
        ];

        // Required fields validation
        $required = ['order_amount', 'order_lines', 'purchase_country', 'purchase_currency'];
        foreach ($required as $field) {
            if (! isset($params[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing.");
            }
        }

        return array_merge($defaultData, $params);
    }

    /**
     * Helper method to build capture data
     *
     * @param  array  $params  Capture parameters
     * @return array Formatted capture data
     */
    public function buildCaptureData(array $params)
    {
        // Required fields validation
        if (! isset($params['captured_amount'])) {
            throw new \InvalidArgumentException("Required field 'captured_amount' is missing.");
        }

        return $params;
    }

    /**
     * Helper method to build refund data
     *
     * @param  array  $params  Refund parameters
     * @return array Formatted refund data
     */
    public function buildRefundData(array $params)
    {
        // Required fields validation
        if (! isset($params['refunded_amount'])) {
            throw new \InvalidArgumentException("Required field 'refunded_amount' is missing.");
        }

        return $params;
    }
}
