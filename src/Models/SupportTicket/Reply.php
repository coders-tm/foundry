<?php

namespace Foundry\Models\SupportTicket;

use Foundry\Concerns\Fileable;
use Foundry\Concerns\SerializeDate;
use Foundry\Database\Factories\ReplyFactory;
use Foundry\Enum\TicketStatus;
use Foundry\Events\SupportTicketReplyCreated;
use Foundry\Foundry;
use Foundry\Models\Notification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    use Fileable, HasFactory, HasUuids, SerializeDate;

    protected $table = 'support_ticket_replies';

    protected $dispatchesEvents = [
        'created' => SupportTicketReplyCreated::class,
    ];

    protected $fillable = [
        'message',
        'support_ticket_id',
        'user_type',
        'user_id',
        'seen',
        'staff_only',
    ];

    protected $with = ['media'];

    protected $casts = [
        'seen' => 'boolean',
    ];

    protected $appends = ['created_time'];

    public function getCreatedTimeAttribute()
    {
        return $this->created_at->format('H:i');
    }

    public function support_ticket()
    {
        return $this->belongsTo(Foundry::$supportTicketModel);
    }

    public function user()
    {
        return $this->morphTo()->withOnly([]);
    }

    public function scopeUnseen($query)
    {
        return $query->where('seen', 0);
    }

    public function byAdmin(): bool
    {
        return str_contains($this->user_type, 'Admin');
    }

    public function renderNotification($type = null): Notification
    {
        $default = $this->byAdmin() ? 'user:support-ticket-reply-notification' : 'admin:support-ticket-reply-notification';
        $template = Notification::default($type ?? $default);

        // Render using NotificationTemplateRenderer for dual-format support
        $rendered = $template->render([
            'user' => $this->user ? $this->user->getShortCodes() : ['name' => 'Guest'],
            'support_ticket' => $this->support_ticket->getShortCodes(),
            'reply' => $this->getShortCodes(),
        ]);

        return $template->fill([
            'subject' => $rendered['subject'],
            'content' => $rendered['content'],
        ]);
    }

    public function getShortCodes(): array
    {
        return [
            'message' => $this->message,
            'user' => $this->user ? $this->user->getShortCodes() : ['name' => 'Guest'],
            'attachments' => $this->media->map(function ($file) {
                return [
                    'name' => $file->name,
                    'url' => $file->url,
                ];
            })->toArray(),
        ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return ReplyFactory::new();
    }

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if ($model->byAdmin()) {
                $model->seen = true;
            }
        });

        static::created(function ($model) {
            if ($model->staff_only) {
                return false;
            }

            if ($model->byAdmin()) {
                $model->support_ticket->update([
                    'status' => TicketStatus::STAFF_REPLIED,
                    'user_archived' => 0,
                ]);
            } else {
                $model->support_ticket->update([
                    'status' => TicketStatus::REPLIED,
                    'is_archived' => 0,
                ]);
            }
        });
    }
}
