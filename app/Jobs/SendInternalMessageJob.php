<?php

namespace App\Jobs;

use App\Mail\InternalMessageMail;
use App\Models\InternalMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInternalMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly InternalMessage $message) {}

    public function handle(): void
    {
        if (! $this->message->send_email) {
            return;
        }

        // Direct message to a single user
        if ($this->message->recipient_user_id) {
            $user = \App\Models\User::find($this->message->recipient_user_id);
            if ($user) {
                Mail::to($user->email, $user->name)
                    ->queue(new InternalMessageMail($this->message, $user));
            }
            return;
        }

        // Broadcast to audience group
        User::query()
            ->when($this->message->audience === 'artists', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'artist')))
            ->when($this->message->audience === 'listeners', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'listener')))
            ->whereNotNull('email_verified_at')
            ->select(['id', 'name', 'email'])
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    Mail::to($user->email, $user->name)
                        ->queue(new InternalMessageMail($this->message, $user));
                }
            });
    }
}
