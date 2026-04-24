<?php

namespace Foundry\Models;

use Foundry\Concerns\Core;
use Foundry\Concerns\Fileable;
use Foundry\Database\Factories\SupportTicketFactory;
use Foundry\Enum\TicketStatus;
use Foundry\Events\SupportTicketCreated;
use Foundry\Foundry;
use Foundry\Models\SupportTicket\Reply;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportTicket extends Model
{
    use Core, Fileable;

    const SOURCE_CONTACT_US = 'contact_us';

    protected $dispatchesEvents = [
        'created' => SupportTicketCreated::class,
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'seen',
        'is_archived',
        'user_archived',
        'source',
        'ticket_number',
    ];

    protected $with = ['last_reply.user', 'user'];

    protected $appends = ['has_unseen'];

    protected $withCount = ['unseen'];

    protected $casts = [
        'status' => TicketStatus::class,
        'seen' => 'boolean',
        'is_archived' => 'boolean',
        'user_archived' => 'boolean',
    ];

    public function getHasUnseenAttribute()
    {
        return $this->unseen_count > 0 || ! $this->seen;
    }

    public function getNameAttribute($value)
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $value;
    }

    public function getPhoneAttribute($value)
    {
        if ($this->user) {
            return $this->user->phone_number;
        }

        return $value;
    }

    public function user()
    {
        return $this->belongsTo(Foundry::$userModel, 'email', 'email')->withOnly([]);
    }

    public function replies()
    {
        return $this->hasMany(Reply::class, 'support_ticket_id')->orderBy('created_at', 'desc');
    }

    public function last_reply()
    {
        return $this->hasOne(Reply::class, 'support_ticket_id')->orderBy('created_at', 'desc');
    }

    public function unseen()
    {
        return $this->hasMany(Reply::class, 'support_ticket_id')->unseen();
    }

    public function markedAsSeen()
    {
        $this->seen = true;
        $this->unseen()->update([
            'seen' => true,
        ]);
        $this->save();

        return $this;
    }

    public function createReply(array $attributes = [])
    {
        $reply = new Reply($attributes);
        $reply->user()->associate(user());

        return $this->replies()->save($reply);
    }

    public function scopeWhereType($query, $type)
    {
        switch ($type) {
            case 'users':
                return $query->whereNotNull('subject');
                break;

            default:
                return $query->whereNull('subject');
                break;
        }
    }

    public function scopeOnlyOwner($query, $user)
    {
        return $query->whereHas('user', function ($q) use ($user) {
            $q->where('id', $user->id);
        });
    }

    public function scopeOnlyUnread($query)
    {
        return $query->where('status', TicketStatus::STAFF_REPLIED);
    }

    public function scopeOnlyActive($query)
    {
        return $query->onlyStatus('Live');
    }

    public function scopeOnlyStatus($query, $status = null)
    {
        switch ($status) {
            case 'Live':
                if (guard('user') && Auth::guard('user')->hasUser()) {
                    return $query->whereUserArchived(0);
                } else {
                    return $query->whereIsArchived(0);
                }
                break;

            case 'Archive':
                if (guard('user') && Auth::guard('user')->hasUser()) {
                    return $query->whereUserArchived(1);
                } else {
                    return $query->whereIsArchived(1);
                }
                break;
        }

        return $query;
    }

    public function scopeSortBy($query, $column = 'created_at', $direction = 'asc')
    {
        switch ($column) {
            case 'last_reply':
                return $query->select('support_tickets.*')
                    ->leftJoin('support_ticket_replies', function ($join) {
                        $join->on('support_ticket_replies.support_ticket_id', '=', 'support_tickets.id');
                    })
                    ->groupBy('support_tickets.id')
                    ->orderBy(DB::raw('support_ticket_replies.created_at IS NULL'), 'desc')
                    ->orderBy(DB::raw('support_ticket_replies.created_at'), $direction ?? 'asc');
                break;

            default:
                return $query->orderBy($column ?: 'created_at', $direction ?? 'asc');
                break;
        }
    }

    public function isContactUs(): bool
    {
        return $this->source === self::SOURCE_CONTACT_US;
    }

    public function renderNotification($type = null): Notification
    {
        $default = empty($this->admin_id) ? 'user:support-ticket-confirmation' : 'user:support-ticket-notification';
        $template = Notification::default($type ?? $default);

        // Use structured data for dual-format support
        $data = [
            'user' => $this->user?->getShortCodes() ?? [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
            ],
            'support_ticket' => $this->getShortCodes(),
        ];

        // Render using NotificationTemplateRenderer
        $rendered = $template->render($data);

        return $template->fill([
            'subject' => $rendered['subject'],
            'content' => $rendered['content'],
        ]);
    }

    public function getShortCodes(): array
    {
        return [
            'id' => $this->id,
            'url' => app_url("support-tickets/{$this->id}?action=edit"),
            'admin_url' => admin_url("support-tickets/{$this->id}?action=edit"),
            'attachments' => $this->media->map(function ($file) {
                return [
                    'name' => $file->name,
                    'url' => $file->url,
                ];
            })->toArray(),
            'subject' => $this->subject,
            'status' => $this->status->value,
            'message' => $this->message,
            'ticket_number' => $this->ticket_number,
        ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return SupportTicketFactory::new();
    }

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->status)) {
                $model->status = TicketStatus::PENDING->value;
            }

            if (empty($model->ticket_number)) {
                do {
                    $ticketNumber = fake()->regexify('[A-Z]{3}-[0-9]{3}-[0-9]{5}');
                } while (static::where('ticket_number', $ticketNumber)->exists());

                $model->ticket_number = $ticketNumber;
            }
        });
    }
}
