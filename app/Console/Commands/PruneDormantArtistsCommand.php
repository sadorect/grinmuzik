<?php

namespace App\Console\Commands;

use App\Mail\DormantArtistWarningMail;
use App\Models\ArtistProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PruneDormantArtistsCommand extends Command
{
    protected $signature = 'artists:prune-dormant {--dry-run : List affected artists without sending emails or making changes}';

    protected $description = 'Warn artists with no published tracks, then demote them to listener status after 30 days.';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            return $this->handleDryRun();
        }

        $warned = 0;

        ArtistProfile::pendingDormancyWarning()->with('user')->each(function (ArtistProfile $artist) use (&$warned) {
            if (! $artist->user) {
                return;
            }

            Mail::to($artist->user->email)->send(new DormantArtistWarningMail($artist));
            $artist->update(['warned_at' => now()]);

            Log::info('Dormant artist warned', ['artist_id' => $artist->id, 'slug' => $artist->slug]);

            $warned++;
        });

        $demoted = 0;

        ArtistProfile::pendingDormancyDemotion()->each(function (ArtistProfile $artist) use (&$demoted) {
            $artist->demoteToListener();

            Log::info('Dormant artist demoted to listener', ['artist_id' => $artist->id, 'slug' => $artist->slug]);

            $demoted++;
        });

        $this->components->info("Warned {$warned} artist(s), demoted {$demoted} artist(s).");

        return self::SUCCESS;
    }

    protected function handleDryRun(): int
    {
        $toWarn = ArtistProfile::pendingDormancyWarning()->get();
        $toDemote = ArtistProfile::pendingDormancyDemotion()->get();

        $this->components->info("Would warn {$toWarn->count()} artist(s):");
        foreach ($toWarn as $artist) {
            $this->line("  - [{$artist->id}] {$artist->slug} (registered {$artist->created_at->toDateString()})");
        }

        $this->components->info("Would demote {$toDemote->count()} artist(s):");
        foreach ($toDemote as $artist) {
            $this->line("  - [{$artist->id}] {$artist->slug} (warned {$artist->warned_at->toDateString()})");
        }

        return self::SUCCESS;
    }
}
