# Support Tickets Domain

## Overview

The Support Tickets domain manages customer support workflows including ticket creation, replies, status tracking, and lifecycle events.

## Core Models

- **`SupportTicket`** (`src/Models/SupportTicket.php`) — Main ticket model with subject, description, status, priority, and customer/user association.
- **`SupportTicket/Reply`** (`src/Models/SupportTicket/Reply.php`) — Individual replies/comments on a ticket.
- **`SupportTicket/Status`** (`src/Models/SupportTicket/Status.php`) — Status history tracking ticket state changes.
- **`TicketStatus` enum** (`src/Enum/TicketStatus.php` or similar) — Defines valid ticket states (open, in-progress, awaiting-response, resolved, closed, etc.).

## Key Workflows

### Creating a Support Ticket

1. Create `SupportTicket` with:
   - `subject` (ticket title)
   - `description` (initial message/problem description)
   - `user_id` (customer who submitted)
   - `status` (initially `open` from `TicketStatus`)
   - `priority` (low, medium, high, critical)
   - Optional: `assignee_id` (support agent), `category` (billing, technical, general)

2. Event `SupportTicketCreated` is dispatched for notifications and logging.

### Adding Replies

1. Create `SupportTicket/Reply` with:
   - `ticket_id` (parent ticket)
   - `user_id` (who replied: customer or support agent)
   - `message` (reply content)
   - Optional: attachments, internal note flag

2. Event `SupportTicketReplyCreated` is dispatched.

3. Update ticket `last_reply_at` timestamp.

4. Update ticket `status` if needed (e.g., from `awaiting-response` to `in-progress`).

### Status Transitions

- **`open`** → Customer submitted ticket, awaiting support response.
- **`in-progress`** → Support agent has acknowledged and is working.
- **`awaiting-response`** → Support has replied, waiting for customer response.
- **`resolved`** → Issue resolved, customer confirmed.
- **`closed`** → Ticket archived (auto-close after inactivity or manual closure).

Transition logic should validate state machine constraints (e.g., can't go directly from `open` to `closed`).

## Database Relations

- `SupportTicket` → `SupportTicket/Reply` (hasMany) — all replies on this ticket
- `SupportTicket` → `SupportTicket/Status` (hasMany) — status change history
- `User` → `SupportTicket` (hasMany) — tickets created by user
- `User` (admin) → `SupportTicket` (hasMany, via `assignee_id`) — tickets assigned to agent

## Events

- **`SupportTicketCreated`** — Fires when ticket is created; trigger notifications to support team.
- **`SupportTicketReplyCreated`** — Fires when reply is added; notify other party (customer if agent replies, agent if customer replies).
- **`SupportTicketResolved`** — Fires when ticket status moves to `resolved`.
- **`SupportTicketClosed`** — Fires when ticket is closed.

## Best Practices

- **State machine**: Use explicit enum for status transitions; validate state changes before persisting.
- **Audit trail**: Record all status changes in `SupportTicket/Status` for visibility and support tracking.
- **Response times**: Track `first_response_time` and `resolution_time` for SLA monitoring.
- **Assignment routing**: Route tickets to appropriate support team member based on category/priority.
- **Attachments**: Support file uploads in tickets and replies for evidence/screenshots (link to `File` model).
- **Notifications**: Notify users on ticket updates via email/in-app (use `NotificationTemplateRenderer`).
- **Escalation**: Auto-escalate unresolved tickets after threshold time or on customer request.

## Common Tasks

### Create a Ticket

```php
$ticket = SupportTicket::create([
    'user_id' => auth()->id(),
    'subject' => 'Subscription not working',
    'description' => 'I was charged but still see free plan features.',
    'priority' => 'high',
    'status' => TicketStatus::OPEN->value,
]);

event(new SupportTicketCreated($ticket));
```

### Reply to Ticket

```php
$reply = $ticket->replies()->create([
    'user_id' => auth()->id(), // or agent id if support
    'message' => 'We are investigating this issue...',
]);

$ticket->update(['status' => TicketStatus::IN_PROGRESS->value]);
event(new SupportTicketReplyCreated($reply, $ticket));
```

### Close Resolved Ticket

```php
$ticket->update(['status' => TicketStatus::CLOSED->value]);
SupportTicket/Status::create([
    'ticket_id' => $ticket->id,
    'status' => TicketStatus::CLOSED->value,
    'changed_at' => now(),
    'changed_by' => auth()->id(),
]);
event(new SupportTicketClosed($ticket));
```

### Query Tickets for Agent Dashboard

```php
// Open tickets awaiting response
$pending = SupportTicket::whereIn('status', [
    TicketStatus::OPEN->value, 
    TicketStatus::AWAITING_RESPONSE->value
])
->orderBy('priority', 'desc')
->orderBy('created_at', 'asc')
->get();
```
