<?php

namespace Foundry\Contracts;

interface PayableInterface
{
    /**
     * Get the total amount to be paid
     */
    public function getGrandTotal(): float;

    /**
     * Get the currency for the payment based on billing address
     */
    public function getCurrency(): string;

    /**
     * Get the amount converted to the gateway currency
     */
    public function getGatewayAmount(): float;

    /**
     * Get the tax total
     */
    public function getTaxTotal(): float;

    /**
     * Get the shipping total
     */
    public function getShippingTotal(): float;

    /**
     * Get the customer email
     */
    public function getCustomerEmail(): ?string;

    /**
     * Get the customer first name
     */
    public function getCustomerFirstName(): ?string;

    /**
     * Get the customer last name
     */
    public function getCustomerLastName(): ?string;

    /**
     * Get the customer name
     */
    public function getCustomerName(): ?string;

    /**
     * Get the customer phone number
     */
    public function getCustomerPhone(): ?string;

    /**
     * Get the billing address
     */
    public function getBillingAddress(): ?array;

    /**
     * Get the shipping address
     */
    public function getShippingAddress(): ?array;

    /**
     * Get the reference ID (token for Checkout, key for Order)
     */
    public function getReferenceId(): string;

    /**
     * Get the line items
     */
    public function getLineItems(): array;

    /**
     * Get metadata for payment gateway
     */
    public function getMetadata(): array;

    /**
     * Get the source model (Checkout or Order)
     *
     * @return mixed
     */
    public function getSource();

    /**
     * Get the source model ID
     */
    public function getSourceId(): ?string;

    /**
     * Get a description for the payment
     */
    public function getDescription(): string;

    /**
     * Check if this is a Checkout instance
     */
    public function isCheckout(): bool;

    /**
     * Check if this is an Order instance
     */
    public function isOrder(): bool;
}
