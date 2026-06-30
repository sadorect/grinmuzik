<?php

namespace App\Jobs;

use App\Mail\InternalMessageMail;
use App\Models\InternalMessage;
use App\Notifications\InternalMessagePushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class SendInternalMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly InternalMessage $message) {}

    public function handle(): void
    {
        if ($this->message->send_email) {
            $this->message
                ->recipientUsers()
                ->whereNotNull('email')
                ->select(['id', 'name', 'email'])
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        Mail::to($user->email, $user->name)
                            ->queue(new InternalMessageMail($this->message, $user));
                    }
                });
        }

        $this->message
            ->recipientUsers()
            ->whereHas('pushSubscriptions')
            ->chunkById(100, function ($users): void {
                Notification::send($users, new InternalMessagePushNotification($this->message));
            });
    }
}
