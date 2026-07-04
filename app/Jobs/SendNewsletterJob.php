<?php

namespace App\Jobs;

use App\Mail\WeeklyNewsletterMail;
use App\Models\NewsletterDraft;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly NewsletterDraft $draft) {}

    public function handle(): void
    {
        User::query()
            ->whereNotNull('email_verified_at')
            ->select(['id', 'name', 'email'])
            ->chunkById(50, function ($users): void {
                foreach ($users as $user) {
                    Mail::to($user->email, $user->name)
                        ->queue(new WeeklyNewsletterMail($this->draft));
                }
            });

        $this->draft->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Log::info('Newsletter sent', [
            'draft_id' => $this->draft->id,
            'subject' => $this->draft->subject,
        ]);
    }
}
