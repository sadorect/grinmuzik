<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Digital Asset Links is what removes the browser URL bar from the Android TWA.
 * Android caches a failed verification, so serving a well-formed file with the
 * wrong contents is worse than serving nothing.
 */
class AssetLinksTest extends TestCase
{
    public function test_it_404s_until_the_fingerprint_is_configured(): void
    {
        config()->set('security.play.app_fingerprint', null);

        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_it_serves_the_statement_once_configured(): void
    {
        config()->set('security.play.package_name', 'com.grinmuzik.app');
        config()->set('security.play.app_fingerprint', 'AA:BB:CC:DD');

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJson([
                [
                    'relation' => ['delegate_permission/common.handle_all_urls'],
                    'target' => [
                        'namespace' => 'android_app',
                        'package_name' => 'com.grinmuzik.app',
                        'sha256_cert_fingerprints' => ['AA:BB:CC:DD'],
                    ],
                ],
            ]);
    }

    public function test_it_supports_more_than_one_fingerprint(): void
    {
        // Needed while rotating keys, and to trust the upload key alongside the
        // Play App Signing key during testing.
        config()->set('security.play.package_name', 'com.grinmuzik.app');
        config()->set('security.play.app_fingerprint', 'AA:BB, CC:DD');

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.target.sha256_cert_fingerprints', ['AA:BB', 'CC:DD']);
    }

    public function test_the_statement_is_not_behind_the_captcha_or_auth(): void
    {
        // The Android verifier is not a browser: it cannot solve a challenge or
        // log in, so anything gating this route breaks TWA verification.
        config()->set('security.play.package_name', 'com.grinmuzik.app');
        config()->set('security.play.app_fingerprint', 'AA:BB:CC:DD');

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');
    }
}
