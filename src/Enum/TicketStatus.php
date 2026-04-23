<?php

namespace Foundry\Enum;

enum TicketStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case REPLIED = 'replied';
    case STAFF_REPLIED = 'staff_replied';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case HOLD = 'hold';
}
