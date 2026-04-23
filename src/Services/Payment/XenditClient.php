<?php

namespace Foundry\Services\Payment;

use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\PaymentMethod\PaymentMethodApi;
use Xendit\PaymentMethod\PaymentMethodParameters;
use Xendit\PaymentRequest\PaymentRequestApi;
use Xendit\PaymentRequest\PaymentRequestParameters;
use Xendit\XenditSdkException;

class XenditClient
{
    /**
     * @var PaymentMethodApi
     */
    protected $pmApiInstance;

    /**
     * @var PaymentRequestApi
     */
    protected $prApiInstance;

    /**
     * @var InvoiceApi
     */
    protected $invoiceApiInstance;

    /**
     * XenditClient constructor.
     */
    public function __construct(array $options = [])
    {
        $secretKey = $options['secret_key'] ?? config('xendit.secret_key');
        if (! $secretKey) {
            throw new \InvalidArgumentException('Xendit secret_key is required.');
        }
        Configuration::setXenditKey($secretKey);
        $this->pmApiInstance = new PaymentMethodApi;
        $this->prApiInstance = new PaymentRequestApi;
        $this->invoiceApiInstance = new InvoiceApi;
    }

    /**
     * Create a payment request
     *
     * @return mixed
     *
     * @throws XenditSdkException
     */
    public function createPaymentRequest(array $params)
    {
        $paymentRequestParameters = new PaymentRequestParameters($params);

        return $this->prApiInstance->createPaymentRequest(
            null, // idempotency_key
            null, // for_user_id
            null, // with_split_rule
            $paymentRequestParameters
        );
    }

    /**
     * Get payment request by ID
     *
     * @param  string  $paymentRequestId
     * @return mixed
     *
     * @throws XenditSdkException
     */
    public function getPaymentRequest($paymentRequestId)
    {
        return $this->prApiInstance->getPaymentRequestByID($paymentRequestId, null);
    }

    /**
     * Create a payment method
     *
     * @return mixed
     *
     * @throws XenditSdkException
     */
    public function createPaymentMethod(array $params)
    {
        $paymentMethodParameters = new PaymentMethodParameters($params);

        return $this->pmApiInstance->createPaymentMethod(
            null, // for_user_id - set to null for main account
            $paymentMethodParameters
        );
    }

    /**
     * Get payment method by ID
     *
     * @param  string  $paymentMethodId
     * @return mixed
     *
     * @throws XenditSdkException
     */
    public function getPaymentMethod($paymentMethodId)
    {
        return $this->pmApiInstance->getPaymentMethodById($paymentMethodId, null);
    }

    /**
     * Create an invoice (simpler alternative to payment requests)
     *
     * @return mixed
     *
     * @throws XenditSdkException
     */
    public function createInvoice(array $params)
    {
        $invoiceRequest = new CreateInvoiceRequest($params);

        return $this->invoiceApiInstance->createInvoice($invoiceRequest);
    }

    /**
     * Get invoice by ID
     *
     * @param  string  $invoiceId
     * @return mixed
     *
     * @throws XenditSdkException
     */
    public function getInvoice($invoiceId)
    {
        return $this->invoiceApiInstance->getInvoiceById($invoiceId);
    }
}
