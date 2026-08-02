<?php

use App\Models\ContentReport;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Community guidelines.
 *
 * Google Play's UGC policy requires published rules that users are held to,
 * alongside the reporting mechanism and the moderation process. The list of
 * prohibitions is generated from ContentReport::reasons() so the rules and the
 * report form can never describe different things.
 */
new #[Layout('layouts.glass-app')] class extends Component
{
    public function with(): array
    {
        return [
            'seo' => [
                'title' => 'Community Guidelines',
                'description' => 'The rules for uploading and interacting on GrinMuzik, and how to report content that breaks them.',
                'canonical' => route('guidelines'),
                'robots' => 'index,follow',
            ],
            'reasons' => ContentReport::reasons(),
        ];
    }
}; ?>

<div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Community Guidelines</h1>

        <p class="mt-3 text-gray-600 dark:text-gray-300">
            GrinMuzik is a place for independent artists to share their work. These
            rules apply to everything you upload or post — tracks, artwork, artist
            profiles and comments. Breaking them can cost you your upload, and
            repeatedly breaking them can cost you your account.
        </p>

        <section class="mt-8 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Don't upload or post</h2>

            <ul class="mt-4 space-y-3 text-gray-600 dark:text-gray-300">
                <li><strong class="text-gray-900 dark:text-white">Music you don't have the rights to.</strong> Only upload work you created or have permission to distribute. This includes samples you haven't cleared.</li>
                <li><strong class="text-gray-900 dark:text-white">Sexual or pornographic content.</strong> Including artwork and profile images.</li>
                <li><strong class="text-gray-900 dark:text-white">Anything involving the sexual abuse or exploitation of children.</strong> We remove this on sight and report it to the appropriate authorities.</li>
                <li><strong class="text-gray-900 dark:text-white">Hate speech or harassment.</strong> Content attacking people over race, ethnicity, religion, disability, sex, gender identity, or sexual orientation.</li>
                <li><strong class="text-gray-900 dark:text-white">Violence or violent extremism.</strong> Including content promoting or glorifying terrorist organisations.</li>
                <li><strong class="text-gray-900 dark:text-white">Illegal or dangerous activity.</strong> Content facilitating the sale of drugs, weapons, or other regulated goods.</li>
                <li><strong class="text-gray-900 dark:text-white">Impersonation.</strong> Don't pass yourself off as another artist or person.</li>
                <li><strong class="text-gray-900 dark:text-white">Spam or misleading content.</strong> Including misrepresenting what a track is to get plays.</li>
            </ul>
        </section>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Reporting something</h2>

            <p class="mt-3 text-gray-600 dark:text-gray-300">
                Every track and artist profile has a <strong>Report</strong> button.
                You don't need an account to use it. Choose the reason that fits
                best — these are the categories we act on:
            </p>

            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($reasons as $label)
                    <li class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ $label }}</li>
                @endforeach
            </ul>
        </section>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">What happens next</h2>

            <ol class="mt-3 list-decimal space-y-2 pl-5 text-gray-600 dark:text-gray-300">
                <li>Every report goes to our moderation queue and is reviewed by a person.</li>
                <li>Reports involving child safety, violence or illegal activity take content out of public view <strong>immediately</strong>, before review.</li>
                <li>Content attracting several independent reports is hidden automatically while it waits to be reviewed.</li>
                <li>If we agree it breaks these rules, the content stays down. If we don't, it goes straight back up — a report is not a verdict.</li>
                <li>We don't tell the person who posted the content who reported it.</li>
            </ol>
        </section>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">If your content was removed</h2>

            <p class="mt-3 text-gray-600 dark:text-gray-300">
                We get it wrong sometimes. If your track was taken down and you
                think that was a mistake,
                <a href="{{ route('contact') }}" class="font-medium text-primary-600 hover:underline">get in touch</a>
                and we'll look at it again. Unpublishing is reversible — your work
                isn't destroyed while a dispute is open.
            </p>
        </section>

        <p class="mt-8 text-sm text-gray-500 dark:text-gray-400">
            See also our
            <a href="{{ route('terms') }}" class="font-medium text-primary-600 hover:underline">Terms of Service</a>
            and
            <a href="{{ route('privacy') }}" class="font-medium text-primary-600 hover:underline">Privacy Policy</a>.
        </p>
    </div>
</div>
