<?php

namespace Foundry\Enum;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case DECLINED = 'declined';
    case DISPUTED = 'disputed';
    case ARCHIVED = 'archived';
    case REFUNDED = 'refunded';
    case MANUAL_VERIFICATION_REQUIRED = 'manual_verification_required';
}
