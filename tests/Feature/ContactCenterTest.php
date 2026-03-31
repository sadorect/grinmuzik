<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessageResource;
use App\Mail\ContactFormMail;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_submission_is_saved_and_emailed(): void
    {
        Mail::fake();

        $component = Volt::test('pages.contact')
            ->set('name', 'Listener One')
            ->set('email', 'listener@example.com')
            ->set('subject', 'Need help with my account')
            ->set('message', 'I need help with my account because the dashboard is not behaving the way I expected it to.')
            ->set('captcha', '9');

        session(['captcha_answer' => 9]);

        $component
            ->call('send')
            ->assertSet('sent', true);

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Listener One',
            'email' => 'listener@example.com',
            'subject' => 'Need help with my account',
            'status' => ContactMessage::STATUS_NEW,
            'source' => 'contact_form',
        ]);

        Mail::assertSent(ContactFormMail::class, function (ContactFormMail $mail): bool {
            return $mail->senderEmail === 'listener@example.com'
                && $mail->messageId !== null;
        });
    }

    public function test_contact_center_is_available_to_admin_accounts(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $this->assertTrue(ContactMessageResource::canViewAny());

        $this->get('/musicdirector/contact-messages')
            ->assertOk()
            ->assertSee('Contact Center');
    }

    public function test_contact_message_reply_archive_and_restore_update_the_message_state(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        $message = ContactMessage::create([
            'name' => 'Support Request',
            'email' => 'support@example.com',
            'subject' => 'Question about uploads',
            'message' => 'How do I update the cover on an existing release?',
            'source' => 'contact_form',
        ]);

        $reply = $message->recordReply($admin, 'Re: Question about uploads', 'Open the track editor and replace the cover image there.');

        $this->assertDatabaseHas('contact_message_replies', [
            'id' => $reply->id,
            'contact_message_id' => $message->id,
            'user_id' => $admin->id,
            'subject' => 'Re: Question about uploads',
        ]);

        $freshMessage = $message->fresh();

        $this->assertSame(ContactMessage::STATUS_REPLIED, $freshMessage->status);
        $this->assertSame(1, $freshMessage->reply_count);
        $this->assertSame($admin->id, $freshMessage->assigned_admin_id);
        $this->assertNotNull($freshMessage->last_replied_at);

        $freshMessage->archive();

        $this->assertSame(ContactMessage::STATUS_ARCHIVED, $freshMessage->fresh()->status);

        $freshMessage->fresh()->unarchive();

        $this->assertSame(ContactMessage::STATUS_REPLIED, $freshMessage->fresh()->status);
        $this->assertNull($freshMessage->fresh()->archived_at);
    }
}
