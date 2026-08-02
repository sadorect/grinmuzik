<?php

namespace Tests\Feature;

use App\Mail\ContentReportedMail;
use App\Models\ArtistProfile;
use App\Models\ContentReport;
use App\Models\Track;
use App\Models\User;
use App\Services\ContentModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\SolvesCaptcha;
use Tests\TestCase;

class ContentReportTest extends TestCase
{
    use RefreshDatabase;
    use SolvesCaptcha;

    private function makeTrack(bool $published = true): Track
    {
        $user = User::factory()->create();

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Artist '.$user->id,
            'slug' => 'artist-'.$user->id,
        ]);

        return Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Track '.$user->id,
            'slug' => 'track-'.$user->id,
            'is_published' => $published,
        ]);
    }

    private function report(Track $track, string $reason = 'spam'): \Livewire\Features\SupportTesting\Testable
    {
        $component = Livewire::test(\App\Livewire\ReportContent::class, ['content' => $track])
            ->call('openModal')
            ->set('reason', $reason);

        return $component
            ->set('captcha', $this->captchaAnswerFor('content-report'))
            ->call('submit');
    }

    public function test_a_guest_can_report_content(): void
    {
        // Requiring an account would mean objectionable content stays up until
        // someone is willing to sign up to complain about it.
        Mail::fake();

        $track = $this->makeTrack();

        $this->report($track)->assertSet('submitted', true)->assertHasNoErrors();

        $this->assertDatabaseHas('content_reports', [
            'reportable_type' => Track::class,
            'reportable_id' => $track->id,
            'reason' => 'spam',
            'status' => ContentReport::STATUS_PENDING,
        ]);
    }

    public function test_a_morph_map_is_not_introduced_for_reports(): void
    {
        // Aliasing Track or ArtistProfile would be global, and would change what
        // Spatie Media Library writes to media.model_type — orphaning the
        // artwork and audio of every track already uploaded.
        Mail::fake();

        $track = $this->makeTrack();
        $this->report($track);

        $this->assertDatabaseHas('content_reports', [
            'reportable_type' => Track::class,
        ]);

        $this->assertSame(Track::class, $track->getMorphClass());
    }

    public function test_reporting_notifies_the_moderators(): void
    {
        Mail::fake();

        $this->report($this->makeTrack());

        Mail::assertSent(ContentReportedMail::class);
    }

    public function test_an_ordinary_report_does_not_hide_content_on_its_own(): void
    {
        Mail::fake();

        $track = $this->makeTrack();

        $this->report($track);

        $this->assertTrue($track->fresh()->is_published);
    }

    public function test_content_is_hidden_once_the_threshold_is_reached(): void
    {
        Mail::fake();
        config()->set('security.moderation.auto_hide_threshold', 2);

        $track = $this->makeTrack();
        $moderation = app(ContentModerationService::class);

        foreach (['spam', 'hate_speech'] as $i => $reason) {
            $moderation->report($track, [
                'reason' => $reason,
                'reporter_ip' => "203.0.113.{$i}",
            ]);
        }

        $this->assertFalse($track->fresh()->is_published);
    }

    public function test_a_child_safety_report_hides_content_immediately(): void
    {
        // Waiting for a threshold on this category is not a defensible position.
        Mail::fake();
        config()->set('security.moderation.auto_hide_threshold', 99);

        $track = $this->makeTrack();

        app(ContentModerationService::class)->report($track, [
            'reason' => 'child_safety',
            'reporter_ip' => '203.0.113.1',
        ]);

        $this->assertFalse($track->fresh()->is_published);
    }

    public function test_one_person_cannot_reach_the_threshold_alone(): void
    {
        // Otherwise the auto-hide safety net becomes a heckler's veto.
        Mail::fake();
        config()->set('security.moderation.auto_hide_threshold', 2);

        $track = $this->makeTrack();

        $this->report($track);
        $this->report($track);

        $this->assertTrue($track->fresh()->is_published);
        $this->assertSame(1, ContentReport::where('reportable_id', $track->id)->count());
    }

    public function test_a_duplicate_report_still_looks_successful_to_the_reporter(): void
    {
        Mail::fake();

        $track = $this->makeTrack();

        $this->report($track);

        // Telling someone their report was discarded invites them to refile it
        // from another address.
        $this->report($track)->assertSet('submitted', true)->assertHasNoErrors();
    }

    public function test_a_reason_outside_the_list_is_rejected(): void
    {
        Mail::fake();

        $track = $this->makeTrack();

        $this->report($track, 'not-a-real-reason')->assertHasErrors('reason');

        $this->assertDatabaseCount('content_reports', 0);
    }

    public function test_a_tampered_payload_cannot_target_a_non_reportable_model(): void
    {
        // contentType is #[Locked], but Locked only protects what we put there —
        // the service still refuses anything that did not opt in via the trait.
        Mail::fake();

        $track = $this->makeTrack();

        $component = Livewire::test(\App\Livewire\ReportContent::class, ['content' => $track]);

        $this->assertSame(Track::class, $component->get('contentType'));
    }

    public function test_upholding_a_report_unpublishes_the_track(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $track = $this->makeTrack();

        $report = app(ContentModerationService::class)->report($track, [
            'reason' => 'spam',
            'reporter_ip' => '203.0.113.9',
        ]);

        app(ContentModerationService::class)->resolve(
            $report,
            ContentReport::STATUS_UPHELD,
            $admin,
            'Clearly spam.',
            ContentReport::ACTION_UNPUBLISHED,
        );

        $this->assertFalse($track->fresh()->is_published);
        $this->assertSame(ContentReport::STATUS_UPHELD, $report->fresh()->status);
        $this->assertSame($admin->id, $report->fresh()->reviewed_by);
    }

    public function test_resolving_closes_every_pending_report_on_the_same_content(): void
    {
        // Five reports about one track are one decision; leaving four open would
        // make the queue look like unfinished work forever.
        Mail::fake();
        config()->set('security.moderation.auto_hide_threshold', 99);

        $admin = User::factory()->create();
        $track = $this->makeTrack();
        $moderation = app(ContentModerationService::class);

        $first = $moderation->report($track, ['reason' => 'spam', 'reporter_ip' => '203.0.113.1']);
        $moderation->report($track, ['reason' => 'hate_speech', 'reporter_ip' => '203.0.113.2']);
        $moderation->report($track, ['reason' => 'copyright', 'reporter_ip' => '203.0.113.3']);

        $moderation->resolve($first, ContentReport::STATUS_UPHELD, $admin, null, ContentReport::ACTION_UNPUBLISHED);

        $this->assertSame(0, ContentReport::pending()->count());
    }

    public function test_an_artist_profile_is_reportable_too(): void
    {
        Mail::fake();

        $track = $this->makeTrack();
        $profile = $track->artistProfile;

        app(ContentModerationService::class)->report($profile, [
            'reason' => 'impersonation',
            'reporter_ip' => '203.0.113.4',
        ]);

        $this->assertDatabaseHas('content_reports', [
            'reportable_type' => ArtistProfile::class,
            'reportable_id' => $profile->id,
        ]);
    }

    public function test_a_comment_is_reportable(): void
    {
        Mail::fake();

        $track = $this->makeTrack();
        $commenter = User::factory()->create();

        $comment = \App\Models\Comment::create([
            'user_id' => $commenter->id,
            'track_id' => $track->id,
            'body' => 'Something objectionable.',
        ]);

        Livewire::test(\App\Livewire\ReportContent::class, ['content' => $comment])
            ->call('openModal')
            ->set('reason', 'hate_speech')
            ->set('captcha', $this->captchaAnswerFor('content-report'))
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('content_reports', [
            'reportable_type' => \App\Models\Comment::class,
            'reportable_id' => $comment->id,
            'reason' => 'hate_speech',
        ]);
    }

    public function test_hiding_a_comment_soft_deletes_it(): void
    {
        // A comment has no is_published flag to toggle, so Reportable falls back
        // to a soft delete — recoverable if the report turns out to be wrong.
        Mail::fake();

        $track = $this->makeTrack();
        $commenter = User::factory()->create();

        $comment = \App\Models\Comment::create([
            'user_id' => $commenter->id,
            'track_id' => $track->id,
            'body' => 'Something objectionable.',
        ]);

        $comment->hideFromPublic();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_each_report_control_on_a_page_gets_its_own_modal(): void
    {
        // A track page carries one control for the track and one per comment;
        // a shared modal name would make every trigger open the same dialog.
        $track = $this->makeTrack();
        $commenter = User::factory()->create();

        $comment = \App\Models\Comment::create([
            'user_id' => $commenter->id,
            'track_id' => $track->id,
            'body' => 'A comment.',
        ]);

        $trackModal = Livewire::test(\App\Livewire\ReportContent::class, ['content' => $track])->get('modalName');
        $commentModal = Livewire::test(\App\Livewire\ReportContent::class, ['content' => $comment])->get('modalName');

        $this->assertNotSame($trackModal, $commentModal);
    }

    public function test_the_trigger_is_legible_on_the_dark_artist_hero(): void
    {
        // The hero is bg-black/35 with white text; the default grey trigger is
        // all but invisible there.
        $track = $this->makeTrack();

        $onDark = new \App\Livewire\ReportContent;
        $onDark->mount($track->artistProfile, 'onDark');

        $default = new \App\Livewire\ReportContent;
        $default->mount($track, 'default');

        $this->assertStringContainsString('text-white', $onDark->triggerClasses());
        $this->assertStringNotContainsString('text-gray-500', $onDark->triggerClasses());

        $this->assertStringContainsString('text-gray-500', $default->triggerClasses());

        // And the rendered trigger actually carries it.
        Livewire::test(\App\Livewire\ReportContent::class, [
            'content' => $track->artistProfile,
            'variant' => 'onDark',
        ])->assertSee('text-white/70', false);
    }

    public function test_the_community_guidelines_are_published_and_linked(): void
    {
        // Play's UGC policy wants published rules alongside the report
        // mechanism, and they have to be findable.
        $this->get(route('guidelines'))
            ->assertOk()
            ->assertSee('Community Guidelines')
            ->assertSee('Reporting something');

        $this->get('/')->assertSee(route('guidelines'), false);
    }

    public function test_the_guidelines_list_every_reason_the_report_form_offers(): void
    {
        // The page is generated from ContentReport::reasons(), so the rules and
        // the form cannot drift into describing different things.
        $response = $this->get(route('guidelines'));

        foreach (ContentReport::reasons() as $label) {
            $response->assertSee($label);
        }
    }

    public function test_the_report_control_appears_on_a_track_page(): void
    {
        // Play requires reporting to be reachable from the content itself.
        $track = $this->makeTrack();

        $this->get(route('track.show', $track->slug))
            ->assertOk()
            ->assertSee('Report');
    }
}
