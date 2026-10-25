<?php

namespace App\Support\Mail;

use InvalidArgumentException;

/**
 * Every email the app sends, with its default wording.
 *
 * The defaults are the copy the code shipped with. Admins edit a copy of them
 * in the Email Templates screen (the `email_templates` table); when a row is
 * missing the default here is used, so a deleted template never breaks a send.
 *
 * Placeholders are written {like_this}. In the body, a paragraph whose
 * placeholder has no value for a given email is left out, which is how one
 * template covers e.g. donations with and without a listener note.
 */
final class EmailTemplateCatalog
{
    /** All editable text fields, in form order. */
    public const FIELDS = ['subject', 'preheader', 'eyebrow', 'title', 'intro', 'body', 'cta_label', 'footer_note'];

    /** Emails whose main message is written by an admin each time: only the framing text is a template. */
    private const FRAMING = ['eyebrow', 'intro', 'cta_label', 'footer_note'];

    /** Placeholders available in every template. */
    public const GLOBAL_PLACEHOLDERS = [
        'site_name' => 'Site name from Site Branding',
        'recipient_name' => "Recipient's name",
    ];

    public static function all(): array
    {
        return self::definitions();
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function get(string $key): array
    {
        return self::definitions()[$key] ?? throw new InvalidArgumentException("Unknown email template [{$key}].");
    }

    /** Fields an admin may edit for this template. */
    public static function fields(string $key): array
    {
        return self::get($key)['fields'] ?? self::FIELDS;
    }

    public static function defaults(string $key): array
    {
        $defaults = self::get($key)['defaults'];

        return array_combine(self::FIELDS, array_map(fn (string $field) => $defaults[$field] ?? null, self::FIELDS));
    }

    private static function definitions(): array
    {
        static $definitions;

        return $definitions ??= [
            // ── Account & security ──────────────────────────────────────────
            'email_verification' => [
                'name' => 'Verify email address',
                'group' => 'Account & security',
                'audience' => 'Any user',
                'description' => 'Sent at sign-up (and on request) with the link that confirms the email address.',
                'placeholders' => ['expires_minutes' => 'Minutes the link stays valid'],
                'sample' => ['expires_minutes' => '60'],
                'defaults' => [
                    'subject' => 'Verify your email address',
                    'preheader' => 'Confirm your email address to finish setting up your account.',
                    'eyebrow' => 'Account Security',
                    'title' => 'Confirm your email address.',
                    'intro' => 'Please click the button below to verify your email address.',
                    'body' => "This link expires in {expires_minutes} minutes.\n\nIf you did not create an account, no further action is required.",
                    'cta_label' => 'Verify Email Address',
                    'footer_note' => 'This security email was sent from {site_name}.',
                ],
            ],
            'password_reset_request' => [
                'name' => 'Password reset link (listener)',
                'group' => 'Account & security',
                'audience' => 'Listener',
                'description' => 'Sent when a listener asks to reset a forgotten password.',
                'placeholders' => ['email' => 'Account email', 'expires_minutes' => 'Minutes the link stays valid'],
                'sample' => ['email' => 'listener@example.com', 'expires_minutes' => '60'],
                'defaults' => [
                    'subject' => 'Reset your password',
                    'preheader' => 'A secure password reset link is ready for your account.',
                    'eyebrow' => 'Account Security',
                    'title' => 'Reset access to your account.',
                    'intro' => 'Use the secure link below to choose a new password and get back into your account.',
                    'body' => "If you did not request a password reset, no further action is needed.\n\nFor safety, this link expires automatically and should only be used on a device you trust.",
                    'cta_label' => 'Choose New Password',
                    'footer_note' => 'This security email was sent from {site_name}.',
                ],
            ],
            'password_reset_request_artist' => [
                'name' => 'Password reset link (artist)',
                'group' => 'Account & security',
                'audience' => 'Artist',
                'description' => 'Sent when an artist asks to reset a forgotten password.',
                'placeholders' => ['email' => 'Account email', 'expires_minutes' => 'Minutes the link stays valid'],
                'sample' => ['email' => 'artist@example.com', 'expires_minutes' => '60'],
                'defaults' => [
                    'subject' => 'Reset your artist account password',
                    'preheader' => 'A secure password reset link is ready for your account.',
                    'eyebrow' => 'Artist Security',
                    'title' => 'Reset access to your artist account.',
                    'intro' => 'Use the secure link below to choose a new password for your artist tools, uploads, and dashboard access.',
                    'body' => "If you did not request a password reset, no further action is needed.\n\nFor safety, this link expires automatically and should only be used on a device you trust.",
                    'cta_label' => 'Choose New Password',
                    'footer_note' => 'This security email was sent from {site_name}.',
                ],
            ],
            'account_deletion_request' => [
                'name' => 'Confirm account deletion',
                'group' => 'Account & security',
                'audience' => 'Any user',
                'description' => 'Sent when someone asks to delete their account; the button confirms the deletion.',
                'placeholders' => ['expires_hours' => 'Hours the link stays valid', 'confirm_url' => 'The confirmation link'],
                'sample' => ['expires_hours' => '24', 'confirm_url' => 'https://example.com/account/delete/confirm/abc123'],
                'defaults' => [
                    'subject' => 'Confirm you want to delete your {site_name} account',
                    'preheader' => 'This link permanently deletes your {site_name} account.',
                    'eyebrow' => 'Account deletion',
                    'title' => 'Confirm account deletion',
                    'intro' => 'This link permanently deletes your {site_name} account.',
                    'body' => "We received a request to delete your {site_name} account. To go ahead, confirm below.\n\n"
                        . "This cannot be undone. Your profile, uploaded tracks and albums, playlists, likes and follows will be permanently removed.\n\n"
                        . "The link expires in {expires_hours} hours. If the button does not work, paste this into your browser:\n{confirm_url}\n\n"
                        . "Didn't request this? Ignore this email and nothing will happen. Your account stays exactly as it is, and the link stops working once it expires. We never delete an account without a confirmation from this mailbox.",
                    'cta_label' => 'Permanently delete my account',
                    'footer_note' => 'This message was sent because a deletion request was made for this address.',
                ],
            ],

            // ── Artist account ──────────────────────────────────────────────
            'account_updated' => [
                'name' => 'Account details updated',
                'group' => 'Artist account',
                'audience' => 'Artist',
                'description' => 'Sent when an artist saves account settings without changing their email.',
                'placeholders' => ['email' => 'Account email'],
                'sample' => ['email' => 'artist@example.com'],
                'defaults' => [
                    'subject' => 'Your artist account was updated',
                    'preheader' => 'A recent account-settings change was saved on your artist account.',
                    'eyebrow' => 'Account Activity',
                    'title' => 'Your account settings were just changed.',
                    'intro' => 'We are sending this confirmation so you always know when details tied to your artist account have been updated.',
                    'body' => 'Your account details were saved successfully.',
                    'cta_label' => 'Review Account',
                ],
            ],
            'account_email_changed' => [
                'name' => 'Account email changed',
                'group' => 'Artist account',
                'audience' => 'Artist',
                'description' => 'Sent when an artist saves account settings that change their email address.',
                'placeholders' => ['email' => 'New account email'],
                'sample' => ['email' => 'artist@example.com'],
                'defaults' => [
                    'subject' => 'Your artist account was updated',
                    'preheader' => 'A recent account-settings change was saved on your artist account.',
                    'eyebrow' => 'Account Activity',
                    'title' => 'Your account settings were just changed.',
                    'intro' => 'We are sending this confirmation so you always know when details tied to your artist account have been updated.',
                    'body' => 'Your email address changed during this update, and the new address is now active on your account.',
                    'cta_label' => 'Review Account',
                ],
            ],
            'password_changed' => [
                'name' => 'Password changed',
                'group' => 'Artist account',
                'audience' => 'Artist',
                'description' => 'Security confirmation when an artist changes their password in settings.',
                'placeholders' => ['email' => 'Account email'],
                'sample' => ['email' => 'artist@example.com'],
                'defaults' => [
                    'subject' => 'Your artist password was changed',
                    'preheader' => 'A password change was recorded on your artist account.',
                    'eyebrow' => 'Security Update',
                    'title' => 'Your password was changed successfully.',
                    'intro' => 'This is a security confirmation for your artist account.',
                    'body' => 'If you did not make this change, reset your password right away and review your sign-in details.',
                    'cta_label' => 'Open Account Settings',
                ],
            ],
            'password_reset_completed' => [
                'name' => 'Password reset completed',
                'group' => 'Artist account',
                'audience' => 'Artist',
                'description' => 'Security confirmation when an artist finishes a password reset.',
                'placeholders' => ['email' => 'Account email'],
                'sample' => ['email' => 'artist@example.com'],
                'defaults' => [
                    'subject' => 'Your artist password was reset',
                    'preheader' => 'A password change was recorded on your artist account.',
                    'eyebrow' => 'Security Update',
                    'title' => 'Your password reset is complete.',
                    'intro' => 'This is a security confirmation for your artist account.',
                    'body' => 'If you did not complete this reset, change your password again immediately and review your account activity.',
                    'cta_label' => 'Open Account Settings',
                ],
            ],
            'artist_profile_created' => [
                'name' => 'Artist profile created',
                'group' => 'Artist account',
                'audience' => 'Artist',
                'description' => 'Sent when someone sets up their artist profile.',
                'placeholders' => ['stage_name' => 'Stage name'],
                'sample' => ['stage_name' => 'Ada Sounds'],
                'defaults' => [
                    'subject' => 'Your artist profile has been created',
                    'preheader' => 'Your artist profile is live inside the dashboard and ready for updates.',
                    'eyebrow' => 'Artist Setup',
                    'title' => 'Your public artist profile is now in place.',
                    'intro' => 'You can keep refining your presence while email verification and approval are completed.',
                    'body' => 'Verify your email to approve the profile automatically, or contact an admin if you need manual help.',
                    'cta_label' => 'Open Artist Dashboard',
                ],
            ],
            'artist_profile_updated' => [
                'name' => 'Artist profile updated',
                'group' => 'Artist account',
                'audience' => 'Artist',
                'description' => 'Sent when an artist saves changes to their public profile.',
                'placeholders' => ['stage_name' => 'Stage name'],
                'sample' => ['stage_name' => 'Ada Sounds'],
                'defaults' => [
                    'subject' => 'Your artist profile was updated',
                    'preheader' => 'Your artist profile changes have been saved.',
                    'eyebrow' => 'Artist Profile',
                    'title' => 'Your public artist details were just refreshed.',
                    'intro' => 'We are confirming this update so you always have a record of what changed on your artist presence.',
                    'body' => 'Listeners will see the latest saved profile information wherever your artist page appears.',
                    'cta_label' => 'View Public Page',
                ],
            ],
            'dormant_artist_warning' => [
                'name' => 'Inactive artist warning',
                'group' => 'Artist account',
                'audience' => 'Artist',
                'description' => 'Sent a week after sign-up to artists who have not published a track. They are moved to listener 30 days later.',
                'placeholders' => ['stage_name' => 'Stage name'],
                'sample' => ['stage_name' => 'Ada Sounds'],
                'defaults' => [
                    'subject' => 'Your artist status on {site_name} is at risk',
                    'preheader' => "We haven't seen a published track from you yet on {site_name}.",
                    'eyebrow' => 'Action needed',
                    'title' => 'Your artist status is at risk',
                    'intro' => "We haven't seen a published track from you yet on {site_name}.",
                    'body' => "You signed up as an artist on {site_name}, but you haven't published a track yet. No worries — it happens!\n\n"
                        . "You have 30 days to upload and publish your first track. If we don't see one by then, your account will be changed from artist to listener. Your account itself stays active either way, and you're always welcome to become an artist again later.\n\n"
                        . 'Ready to share your music? Just hit the button below to get started.',
                    'cta_label' => 'Upload a track',
                    'footer_note' => 'You are receiving this message because you registered as an artist on {site_name}.',
                ],
            ],

            // ── Tracks & releases ───────────────────────────────────────────
            'track_uploaded' => [
                'name' => 'Track uploaded',
                'group' => 'Tracks & releases',
                'audience' => 'Artist',
                'description' => 'Sent after an artist uploads a track.',
                'placeholders' => [
                    'track_title' => 'Track title',
                    'visibility_message' => 'One sentence saying whether the track is live, scheduled (with the time) or a draft',
                ],
                'sample' => ['track_title' => 'Midnight Drive', 'visibility_message' => 'Because this release is published, it can start appearing across discovery surfaces right away.'],
                'defaults' => [
                    'subject' => 'Track uploaded: {track_title}',
                    'preheader' => 'A new track has been uploaded to your artist account.',
                    'eyebrow' => 'Catalog Update',
                    'title' => 'Your track has been added to your catalog.',
                    'intro' => 'Your latest upload is now saved inside your artist workspace.',
                    'body' => '{visibility_message}',
                    'cta_label' => 'Manage Track',
                ],
            ],
            'track_conversion_completed' => [
                'name' => 'Oversized upload converted',
                'group' => 'Tracks & releases',
                'audience' => 'Artist',
                'description' => 'Sent when an upload that was over the size limit has been converted and saved.',
                'placeholders' => [
                    'track_title' => 'Track title',
                    'visibility_message' => 'One sentence saying whether the track is live, scheduled (with the time) or a draft',
                ],
                'sample' => ['track_title' => 'Midnight Drive', 'visibility_message' => 'This release is currently saved as a draft until you choose to publish it.'],
                'defaults' => [
                    'subject' => 'Track ready: {track_title}',
                    'preheader' => 'Your upload was converted and added to your catalog.',
                    'eyebrow' => 'Upload Converted',
                    'title' => 'Your track was converted and added to your catalog.',
                    'intro' => 'Your file was larger than the current upload limit, so we automatically converted it to a supported size and finished the upload for you.',
                    'body' => '{visibility_message}',
                    'cta_label' => 'Manage Track',
                ],
            ],
            'track_conversion_failed' => [
                'name' => 'Oversized upload failed',
                'group' => 'Tracks & releases',
                'audience' => 'Artist',
                'description' => 'Sent when an oversized upload could not be converted. Nothing is saved.',
                'placeholders' => [
                    'track_title' => 'Track title',
                    'failure_reason' => 'Why it failed (compression limit, or a general processing error)',
                    'limit' => 'Current upload size limit',
                ],
                'sample' => ['track_title' => 'Midnight Drive', 'failure_reason' => 'We could not compress this audio file below the 30 MB limit, even at our lowest quality setting.', 'limit' => '30 MB'],
                'defaults' => [
                    'subject' => 'Upload could not be completed: {track_title}',
                    'preheader' => 'Your upload could not be completed.',
                    'eyebrow' => 'Upload Failed',
                    'title' => 'We could not finish converting your upload.',
                    'intro' => '{failure_reason}',
                    'body' => 'Nothing was saved. Please export a smaller file and upload it again.',
                    'cta_label' => 'Try Again',
                ],
            ],
            'track_updated' => [
                'name' => 'Track updated',
                'group' => 'Tracks & releases',
                'audience' => 'Artist',
                'description' => 'Sent when an artist saves changes to a track.',
                'placeholders' => ['track_title' => 'Track title'],
                'sample' => ['track_title' => 'Midnight Drive'],
                'defaults' => [
                    'subject' => 'Track updated: {track_title}',
                    'preheader' => 'An existing track on your artist account was updated.',
                    'eyebrow' => 'Catalog Update',
                    'title' => 'Your track changes have been saved.',
                    'intro' => 'The latest edits to this release are now reflected across your artist tools.',
                    'body' => 'This includes updates like title, cover art, visibility, pricing, or audio replacement.',
                    'cta_label' => 'Review Track',
                ],
            ],
            'scheduled_release_live' => [
                'name' => 'Scheduled release is live',
                'group' => 'Tracks & releases',
                'audience' => 'Artist',
                'description' => 'Sent when a scheduled track or album reaches its release time. An album covers the tracks released with it.',
                'placeholders' => [
                    'release_title' => 'Track or album title',
                    'release_kind' => 'track / album / ep / single (lower case)',
                    'release_kind_label' => 'Track / Album / Ep / Single (capitalised)',
                    'tracks_with_album' => 'e.g. "3 tracks" released together with an album (paragraph skipped when none)',
                ],
                'sample' => ['release_title' => 'Next Era', 'release_kind' => 'album', 'release_kind_label' => 'Album', 'tracks_with_album' => '3 tracks'],
                'defaults' => [
                    'subject' => 'Now live: {release_title}',
                    'preheader' => '"{release_title}" is now live on {site_name}.',
                    'eyebrow' => 'Scheduled Release',
                    'title' => 'Your scheduled {release_kind} is now live.',
                    'intro' => '"{release_title}" reached its release time and is now visible to listeners.',
                    'body' => "{tracks_with_album} scheduled with it went live at the same time.\n\nShare it with your listeners now that it can be found across {site_name}.",
                    'cta_label' => 'View {release_kind_label}',
                ],
            ],

            // ── Milestones & support ────────────────────────────────────────
            'donation_received' => [
                'name' => 'Support received',
                'group' => 'Milestones & support',
                'audience' => 'Artist',
                'description' => 'Sent when a listener completes a donation or track unlock.',
                'placeholders' => [
                    'amount' => 'Amount, e.g. $5.00',
                    'track_title' => 'Track the support was for (paragraph skipped when none)',
                    'listener_note' => "Listener's message (paragraph skipped when none)",
                ],
                'sample' => ['amount' => '$5.00', 'track_title' => 'Midnight Drive', 'listener_note' => 'Love this one!'],
                'defaults' => [
                    'subject' => 'New support received for your music',
                    'preheader' => 'A completed donation has been recorded for your artist account.',
                    'eyebrow' => 'Support Update',
                    'title' => 'A listener just supported your catalog.',
                    'intro' => 'A completed donation was recorded on your artist account.',
                    'body' => "The support was attached to \"{track_title}\".\n\nListener note: \"{listener_note}\"",
                    'cta_label' => 'Open Artist Dashboard',
                ],
            ],
            'track_milestone' => [
                'name' => 'Track milestone',
                'group' => 'Milestones & support',
                'audience' => 'Artist',
                'description' => 'Sent once per threshold when a track reaches a plays, downloads or likes milestone.',
                'placeholders' => ['track_title' => 'Track title', 'threshold' => 'Milestone reached, e.g. 1,000', 'metric' => 'plays / downloads / likes'],
                'sample' => ['track_title' => 'Midnight Drive', 'threshold' => '1,000', 'metric' => 'plays'],
                'defaults' => [
                    'subject' => 'Milestone reached: {threshold} {metric} on {track_title}',
                    'preheader' => 'A track in your catalog just crossed a new {metric} milestone.',
                    'eyebrow' => 'Track Milestone',
                    'title' => 'Your track just crossed {threshold} {metric}.',
                    'intro' => '"{track_title}" is building momentum inside your catalog.',
                    'body' => 'Keep promoting the release and updating the rest of your catalog to turn this attention into the next spike.',
                    'cta_label' => 'Manage Track',
                ],
            ],
            'follower_milestone' => [
                'name' => 'Follower milestone',
                'group' => 'Milestones & support',
                'audience' => 'Artist',
                'description' => 'Sent once per threshold when an artist reaches a follower milestone.',
                'placeholders' => ['stage_name' => 'Stage name', 'threshold' => 'Milestone reached, e.g. 100'],
                'sample' => ['stage_name' => 'Ada Sounds', 'threshold' => '100'],
                'defaults' => [
                    'subject' => 'Milestone reached: {threshold} followers',
                    'preheader' => 'Your artist profile just crossed a new follower milestone.',
                    'eyebrow' => 'Audience Milestone',
                    'title' => 'You just crossed {threshold} followers.',
                    'intro' => 'Your audience keeps growing and this is a strong signal that listeners are coming back for more.',
                    'body' => 'Keep your catalog moving and your public profile current to turn this momentum into the next milestone.',
                    'cta_label' => 'View Public Page',
                ],
            ],
            'donation_milestone' => [
                'name' => 'Support milestone',
                'group' => 'Milestones & support',
                'audience' => 'Artist',
                'description' => 'Sent once per threshold when total support for an artist passes a milestone.',
                'placeholders' => ['stage_name' => 'Stage name', 'threshold' => 'Milestone passed, e.g. $100'],
                'sample' => ['stage_name' => 'Ada Sounds', 'threshold' => '$100'],
                'defaults' => [
                    'subject' => 'Milestone reached: {threshold} in support',
                    'preheader' => 'Your artist account just crossed a new direct-support milestone.',
                    'eyebrow' => 'Support Milestone',
                    'title' => 'Your supporters have pushed you past {threshold}.',
                    'intro' => 'That is a meaningful signal that your music is connecting strongly enough for listeners to contribute directly.',
                    'body' => 'Keep an eye on the dashboard for which tracks are converting that support most consistently.',
                    'cta_label' => 'Open Artist Dashboard',
                ],
            ],

            // ── Sent to admins ──────────────────────────────────────────────
            'contact_form_received' => [
                'name' => 'New contact form message',
                'group' => 'Sent to admins',
                'audience' => 'Site admins',
                'description' => 'Delivered to the site mailbox when someone uses the contact form. Reply goes straight to the sender.',
                'placeholders' => ['sender_name' => "Sender's name", 'message_subject' => 'Subject they entered', 'message' => 'Their message'],
                'sample' => ['sender_name' => 'Jane Doe', 'message_subject' => 'Partnership', 'message' => 'Hello, I would like to talk about a collaboration.'],
                'defaults' => [
                    'subject' => '[{site_name} Contact] {message_subject}',
                    'preheader' => 'New message from {sender_name}.',
                    'eyebrow' => 'Contact form',
                    'title' => 'New Contact Message',
                    'intro' => 'Submitted via the {site_name} contact form',
                    'body' => "{message}\n\nHit Reply to respond directly to {sender_name}.",
                    'cta_label' => 'Open Contact Center',
                ],
            ],
            'content_reported' => [
                'name' => 'Content reported',
                'group' => 'Sent to admins',
                'audience' => 'Site admins',
                'description' => 'Sent to moderators when a listener reports content.',
                'placeholders' => ['reason' => 'Report reason', 'details' => "Reporter's details (paragraph skipped when none)"],
                'sample' => ['reason' => 'Spam', 'details' => 'Same track uploaded five times.'],
                'defaults' => [
                    'subject' => 'Content reported on {site_name}: {reason}',
                    'preheader' => 'A new content report is waiting for review.',
                    'eyebrow' => 'Moderation',
                    'title' => 'Content reported',
                    'intro' => '{reason}',
                    'body' => "Details: {details}\n\nReview it in the admin panel under Moderation → Content Reports.",
                    'cta_label' => 'Review report',
                ],
            ],
            'content_reported_urgent' => [
                'name' => 'Content reported (urgent)',
                'group' => 'Sent to admins',
                'audience' => 'Site admins',
                'description' => 'Sent for report categories that hide the content immediately on a single report.',
                'placeholders' => ['reason' => 'Report reason', 'details' => "Reporter's details (paragraph skipped when none)"],
                'sample' => ['reason' => 'Child safety', 'details' => 'Inappropriate cover art.'],
                'defaults' => [
                    'subject' => '[URGENT] Content reported on {site_name}: {reason}',
                    'preheader' => 'An urgent content report is waiting for review.',
                    'eyebrow' => 'Urgent',
                    'title' => 'Urgent content report',
                    'intro' => '{reason}',
                    'body' => "This category is auto-hidden on a single report. The content is already out of public view and is waiting on your review.\n\n"
                        . "Details: {details}\n\nReview it in the admin panel under Moderation → Content Reports.",
                    'cta_label' => 'Review report',
                ],
            ],

            // ── Written by an admin each time (framing only) ────────────────
            'contact_reply' => [
                'name' => 'Contact Center reply',
                'group' => 'Admin-written messages',
                'audience' => 'Person who used the contact form',
                'description' => 'Wraps the reply an admin writes in the Contact Center. The subject and message come from the reply itself.',
                'fields' => self::FRAMING,
                'placeholders' => [],
                'sample' => [],
                'defaults' => [
                    'eyebrow' => '{site_name} Contact Desk',
                    'intro' => 'This reply was sent in response to your message to the {site_name} team.',
                    'footer_note' => 'You are receiving this email because you contacted {site_name} through the site contact form.',
                ],
            ],
            'internal_message' => [
                'name' => 'Broadcast message',
                'group' => 'Admin-written messages',
                'audience' => 'Selected users',
                'description' => 'Wraps messages sent from Communication → Internal Messages. The subject and message come from the broadcast.',
                'fields' => self::FRAMING,
                'placeholders' => [],
                'sample' => [],
                'defaults' => [
                    'eyebrow' => 'Message from {site_name}',
                    'intro' => 'A message has been sent to you from the {site_name} team.',
                    'cta_label' => 'Visit {site_name}',
                    'footer_note' => 'You are receiving this message because you are a member of {site_name}.',
                ],
            ],
            'newsletter' => [
                'name' => 'Newsletter',
                'group' => 'Admin-written messages',
                'audience' => 'All members',
                'description' => 'Wraps newsletters from the Newsletter Generator. The subject, period label and content come from the draft.',
                'fields' => ['intro', 'footer_note'],
                'placeholders' => [],
                'sample' => [],
                'defaults' => [
                    'intro' => 'The latest from {site_name}.',
                    'footer_note' => 'You are receiving this newsletter because you are a member of {site_name}.',
                ],
            ],
            'artist_admin_notice' => [
                'name' => 'Admin notice to an artist',
                'group' => 'Admin-written messages',
                'audience' => 'Artist',
                'description' => 'Wraps notices sent to an artist from the admin API (e.g. approval decisions). The subject and message come from the notice.',
                'fields' => ['eyebrow', 'footer_note'],
                'placeholders' => [],
                'sample' => [],
                'defaults' => [
                    'eyebrow' => 'Message from the team',
                    'footer_note' => '— The {site_name} Team',
                ],
            ],
        ];
    }
}
