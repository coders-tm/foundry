<?php

namespace Foundry\Services;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class PaymentMethod implements Arrayable, Jsonable, JsonSerializable
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $label = null,
        public ?string $provider = null,
        public ?string $logo = null,
        public ?string $publicKey = null,
        public ?string $environment = null,
        public ?string $paymentInstructions = null,
        public ?string $additionalDetails = null,
        public array $methods = [],
        public ?string $transactionFee = null,
        public bool $enabled = true,
        public int $order = 99
    ) {}

    public static function fromArray(array $data): static
    {
        $provider = $data['provider'] ?? null;

        $publicKey = $data['public_key'] ?? match ($provider) {
            PaymentProvider::STRIPE => $data['key'] ?? null,
            PaymentProvider::PAYPAL => $data['client_id'] ?? null,
            PaymentProvider::RAZORPAY => $data['key_id'] ?? null,
            PaymentProvider::PADDLE => $data['client_token'] ?? null,
            PaymentProvider::ALIPAY => $data['app_id'] ?? null,
            PaymentProvider::MERCADOPAGO,
            PaymentProvider::PAYSTACK,
            PaymentProvider::XENDIT,
            PaymentProvider::FLUTTERWAVE => $data['public_key'] ?? null,
            default => $data['public_key'] ?? $data['client_id'] ?? null,
        };

        $environment = $data['environment'] ?? $data['mode'] ?? (
            isset($data['test_mode']) ? ($data['test_mode'] ? 'sandbox' : 'live') : null
        );

        return new static(
            id: $data['id'] ?? $data['provider'] ?? null,
            name: $data['name'] ?? $data['provider'] ?? '',
            label: $data['label'] ?? $data['name'] ?? $data['provider'] ?? '',
            provider: $provider,
            logo: $data['logo'] ?? null,
            publicKey: $publicKey,
            environment: $environment,
            paymentInstructions: $data['payment_instructions'] ?? null,
            additionalDetails: $data['additional_details'] ?? null,
            methods: $data['methods'] ?? [],
            transactionFee: $data['transaction_fee'] ?? null,
            enabled: (bool) ($data['enabled'] ?? true),
            order: (int) ($data['order'] ?? 99)
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'provider' => $this->provider,
            'logo' => $this->logo,
            'public_key' => $this->publicKey,
            'environment' => $this->environment,
            'payment_instructions' => $this->paymentInstructions,
            'additional_details' => $this->additionalDetails,
            'methods' => $this->methods,
            'transaction_fee' => $this->transactionFee,
            'enabled' => $this->enabled,
            'order' => $this->order,
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
