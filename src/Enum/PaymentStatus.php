<?php

namespace Foundry\Enum;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case SUCCESS = 'payment_success';
    case FAILED = 'failed';
    case PAYMENT_FAILED = 'payment_failed';
    case PAYMENT_PENDING = 'payment_pending';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_PAID = 'partially_paid';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case PAID = 'paid';
    case VOIDED = 'voided';
}
