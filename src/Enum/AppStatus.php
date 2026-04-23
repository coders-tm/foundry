<?php

namespace Foundry\Enum;

enum AppStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DEACTIVE = 'deactive';
    case HOLD = 'hold';
    case LOST = 'lost';
    case PENDING = 'pending';
    case REPLIED = 'replied';
    case STAFF_REPLIED = 'staff_replied';
    case COMPLETED = 'completed';
    case ONGOING = 'ongoing';
    case RESOLVED = 'resolved';
}
