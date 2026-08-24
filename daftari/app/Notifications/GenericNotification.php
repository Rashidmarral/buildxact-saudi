<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A single reusable notification for the in-app bell: every trigger point
 * (payment received, subscription expiring, team member joined, ...) just
 * supplies title/body/url instead of each needing its own class + view.
 */
class GenericNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly string $icon = 'bell',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}
