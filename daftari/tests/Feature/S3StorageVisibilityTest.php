<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Third-party audit finding SEC-04 (High): switching the 'public' storage
 * disk to S3 (Platform Settings → Storage) set 'visibility' => 'public',
 * telling the S3 driver to give every uploaded object a public-read ACL by
 * default — on a bucket that honors object ACLs, that makes a private
 * tenant document (receipts, bank statements, CR/VAT certificates)
 * fetchable directly from its S3 URL with no authorization at all,
 * completely bypassing FileServeController. Fixed to 'private'; the
 * controller streams objects server-side with its own stored credentials
 * regardless of ACL, so authorized access is unaffected.
 */
class S3StorageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function configureStorageDriver(): void
    {
        $provider = new AppServiceProvider(app());
        $method = new ReflectionMethod($provider, 'configureStorageDriver');
        $method->setAccessible(true);
        $method->invoke($provider);
    }

    public function test_enabling_s3_storage_uses_private_visibility_not_public(): void
    {
        Setting::set('storage_driver', 's3');
        Setting::set('storage_s3_key', 'AKIAEXAMPLEKEY');
        Setting::set('storage_s3_secret', 'example-secret');
        Setting::set('storage_s3_bucket', 'daftari-documents');
        Setting::set('storage_s3_region', 'me-south-1');

        $this->configureStorageDriver();

        $this->assertSame('s3', config('filesystems.disks.public.driver'));
        $this->assertSame('private', config('filesystems.disks.public.visibility'));
    }

    public function test_an_unconfigured_installation_still_uses_the_default_local_disk(): void
    {
        $this->configureStorageDriver();

        $this->assertSame('local', config('filesystems.disks.public.driver'));
    }

    public function test_s3_mode_with_missing_credentials_falls_back_to_local_rather_than_a_broken_config(): void
    {
        Setting::set('storage_driver', 's3');
        // No key/secret/bucket/region set.

        $this->configureStorageDriver();

        $this->assertSame('local', config('filesystems.disks.public.driver'));
    }
}
