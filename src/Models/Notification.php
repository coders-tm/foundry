<?php

namespace Foundry\Models;

use Foundry\Database\Factories\NotificationFactory;
use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Traits\CoreBase;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use CoreBase;

    protected $table = 'notification_templates';

    protected $fillable = [
        'label',
        'subject',
        'type',
        'content',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return NotificationFactory::new();
    }

    public function markAsDefault()
    {
        $this->update(['is_default' => 1]);

        static::where('type', $this->type)
            ->where('id', '<>', $this->id)
            ->update(['is_default' => 0]);

        return $this;
    }

    public static function default($type = null): static
    {
        return static::where('type', $type)
            ->orderBy('is_default')
            ->firstOrFail();
    }

    public function duplicate()
    {
        $template = $this->replicate(['is_default']);

        $template->save();

        return $template->fresh();
    }

    /**
     * Render template with data
     */
    public function render(array $data = []): array
    {
        $renderer = app(NotificationTemplateRenderer::class);

        return [
            'subject' => $renderer->render($this->subject, $data),
            'content' => $renderer->render($this->content, $data),
        ];
    }

    /**
     * Validate template syntax
     */
    public function validate(): array
    {
        $renderer = app(NotificationTemplateRenderer::class);

        return [
            'subject' => $renderer->validate($this->subject),
            'content' => $renderer->validate($this->content),
        ];
    }
}
