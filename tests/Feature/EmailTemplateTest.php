<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Mail\AccountDeletionRequestMail;
use App\Mail\ContactFormMail;
use App\Mail\ContentReportedMail;
use App\Mail\DormantArtistWarningMail;
use App\Mail\EmailTemplateTestMail;
use App\Models\ArtistProfile;
use App\Models\ContentReport;
use App\Models\EmailTemplate;
use App\Models\Track;
use App\Models\User;
use App\Notifications\ArtistNotification;
use App\Notifications\Artist\ArtistActivityNotification;
use App\Notifications\Auth\BrandedVerifyEmail;
use App\Support\ArtistActivityNotifier;
use App\Support\Mail\EmailTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_catalog_email_starts_with_its_default_wording(): void
    {
        $this->assertSame(count(EmailTemplateCatalog::all()), EmailTemplate::count());
        $this->assertFalse(EmailTemplate::all()->contains(fn (EmailTemplate $t) => $t->isCustomised()));
    }

    #[Test]
    public function every_template_renders_with_its_sample_data_and_no_stray_placeholders(): void
    {
        foreach (array_keys(EmailTemplateCatalog::all()) as $key) {
            $mail = new EmailTemplateTestMail($key, 'Sample Admin');
            $html = $mail->render();
            $known = array_keys(EmailTemplateCatalog::get($key)['placeholders'] + EmailTemplateCatalog::GLOBAL_PLACEHOLDERS);

            foreach ($known as $name) {
                $this->assertStringNotContainsString('{' . $name . '}', $html, "{$key} left {{$name}} unfilled");
            }
        }
    }

    #[Test]
    public function an_edited_template_changes_the_email_that_is_sent(): void
    {
        Notification::fake();
        [$artist, $profile] = $this->createArtist();
        $track = $this->makeTrack($profile, 'Midnight Drive');

        EmailTemplate::where('key', 'track_updated')->first()->update([
            'subject' => 'Saved: {track_title}',
            'body' => "First paragraph for {recipient_name}.\n\nSecond paragraph.",
        ]);

        app(ArtistActivityNotifier::class)->sendTrackUpdated($track);

        Notification::assertSentTo($artist, ArtistActivityNotification::class, function (ArtistActivityNotification $n) use ($artist) {
            return $n->subject === 'Saved: Midnight Drive'
                && $n->messageLines === ["First paragraph for {$artist->name}.", 'Second paragraph.'];
        });
    }

    #[Test]
    public function paragraphs_whose_placeholder_has_no_value_are_left_out(): void
    {
        $both = EmailTemplate::copy('donation_received', ['track_title' => 'Song', 'listener_note' => 'Great!']);
        $noteOnly = EmailTemplate::copy('donation_received', ['track_title' => null, 'listener_note' => 'Great!']);

        $this->assertCount(2, $both->lines);
        $this->assertSame(['Listener note: "Great!"'], $noteOnly->lines);
    }

    #[Test]
    public function a_deleted_template_falls_back_to_the_default_and_unknown_placeholders_stay_visible(): void
    {
        EmailTemplate::where('key', 'track_updated')->delete();
        $this->assertSame('Track updated: Song', EmailTemplate::copy('track_updated', ['track_title' => 'Song'])->subject);

        EmailTemplate::syncCatalog();
        EmailTemplate::where('key', 'track_updated')->first()->update(['subject' => 'Hi {typo_here}']);
        $this->assertSame('Hi {typo_here}', EmailTemplate::copy('track_updated')->subject);
    }

    #[Test]
    public function framing_only_templates_ignore_fields_they_do_not_expose(): void
    {
        EmailTemplate::where('key', 'newsletter')->first()->update(['intro' => 'Fresh from {site_name}.', 'subject' => 'ignored']);

        $copy = EmailTemplate::copy('newsletter');

        $this->assertSame('Fresh from ' . $this->siteName() . '.', $copy->intro);
        $this->assertSame('', $copy->subject);
    }

    #[Test]
    public function standalone_emails_use_their_templates(): void
    {
        [, $profile] = $this->createArtist();
        EmailTemplate::where('key', 'dormant_artist_warning')->first()->update(['title' => 'Still there, {stage_name}?']);

        $dormant = (new DormantArtistWarningMail($profile))->render();
        $this->assertStringContainsString('Still there, ' . e($profile->display_name) . '?', $dormant);
        $this->assertStringContainsString(route('artist.upload-track'), $dormant);

        $deletion = new AccountDeletionRequestMail('Jo', 'https://example.test/confirm/xyz', 24);
        $deletion->assertHasSubject('Confirm you want to delete your ' . $this->siteName() . ' account');
        $deletion->assertSeeInHtml('https://example.test/confirm/xyz');
        $deletion->assertSeeInHtml('The link expires in 24 hours.');

        $contact = new ContactFormMail('Jane', 'jane@example.com', 'Hello', "Line one\nLine two", 7);
        $contact->assertHasSubject('[' . $this->siteName() . ' Contact] Hello');
        $contact->assertHasReplyTo('jane@example.com');
        $contact->assertSeeInHtml('#7');
    }

    #[Test]
    public function urgent_content_reports_keep_the_urgent_subject_prefix(): void
    {
        [, $profile] = $this->createArtist();
        $track = $this->makeTrack($profile, 'Flagged');
        $urgentReason = ContentReport::urgentReasons()[0];

        $report = ContentReport::create([
            'reportable_type' => Track::class,
            'reportable_id' => $track->id,
            'reason' => $urgentReason,
            'status' => ContentReport::STATUS_PENDING,
        ]);

        (new ContentReportedMail($report))->assertHasSubject('[URGENT] Content reported on ' . $this->siteName() . ': ' . $report->reasonLabel());
    }

    #[Test]
    public function verification_and_admin_notices_use_the_branded_templates(): void
    {
        $user = User::factory()->unverified()->create();
        Notification::fake();

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, BrandedVerifyEmail::class, function (BrandedVerifyEmail $n) use ($user) {
            $mail = $n->toMail($user);

            return $mail->subject === 'Verify your email address' && str_contains($mail->viewData['ctaUrl'], '/verify-email/');
        });

        $notice = (new ArtistNotification('Your profile was approved', 'Welcome aboard.'))->toMail($user);
        $this->assertSame('Your profile was approved', $notice->subject);
        $this->assertSame('— The ' . $this->siteName() . ' Team', $notice->viewData['footerNote']);
    }

    #[Test]
    public function admins_can_edit_test_and_reset_a_template(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $this->get('/musicdirector/email-templates')->assertOk()->assertSee('Track uploaded');

        $template = EmailTemplate::where('key', 'track_uploaded')->first();

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm(['subject' => 'New upload: {track_title}'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('sendTest');

        $this->assertTrue($template->fresh()->isCustomised());
        Mail::assertSent(EmailTemplateTestMail::class, fn (EmailTemplateTestMail $m) => $m->hasTo($admin->email)
            && $m->envelope()->subject === '[Test] New upload: Midnight Drive');

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('resetToDefault');

        $this->assertFalse($template->fresh()->isCustomised());
    }

    #[Test]
    public function listeners_cannot_open_the_email_templates_screen(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('listener', 'web');
        $user->assignRole('listener');

        $this->actingAs($user)->get('/musicdirector/email-templates')->assertForbidden();
    }

    protected function siteName(): string
    {
        return \App\Models\SiteSetting::current()?->effective_site_name ?: config('app.name');
    }

    protected function createArtist(): array
    {
        $user = User::factory()->create();
        Role::findOrCreate('artist', 'web');
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Template Artist',
            'slug' => 'template-artist-' . $user->id,
            'is_approved' => true,
            'is_active' => true,
        ]);

        return [$user, $profile];
    }

    protected function makeTrack(ArtistProfile $profile, string $title): Track
    {
        return Track::create([
            'user_id' => $profile->user_id,
            'artist_profile_id' => $profile->id,
            'title' => $title,
            'slug' => str($title)->slug() . '-' . $profile->id,
            'is_published' => true,
            'is_free' => true,
        ]);
    }
}
