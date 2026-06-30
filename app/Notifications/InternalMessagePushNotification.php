<?php

namespace App\Notifications;

use App\Models\InternalMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class InternalMessagePushNotification extends Notification
{
    use Queueable;

    public function __construct(public InternalMessage $message) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->message->subject)
            ->body(Str::limit(strip_tags($this->message->interpolate($this->message->body, $notifiable)), 120))
            ->icon('/pwa-icons/manifest-icon-192.maskable.png')
            ->tag("internal-message-{$this->message->id}")
            ->data(['url' => route('inbox')]);
    }
}
