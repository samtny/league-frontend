<?php

namespace Tests\Feature;

use App\Association;
use App\User;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssociationUpdateSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $association;

    public function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::create([
            'name' => 'Assoc Admin',
            'email' => 'assocadmin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->association = Association::create([
            'name' => 'Test Association',
            'user_id' => $this->user->id,
            'subdomain' => 'testassoc',
        ]);

        // Matches AssociationAdminTest's setup: a per-tenant assocadmin,
        // not a superadmin. This is the realistic attacker profile. Granted
        // directly rather than via BouncerSeeder to keep the test isolated -
        // 'view-admin-pages' is required by the outer CheckAdmin middleware,
        // 'manage' (toManage) by the inner EnsureManagesAssociation middleware.
        Bouncer::assign('assocadmin')->to($this->user);
        Bouncer::allow($this->user)->to('view-admin-pages');
        Bouncer::allow($this->user)->toManage($this->association);
    }

    private function makeZip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return new UploadedFile($path, 'favicon.zip', 'application/zip', null, true);
    }

    private function baseFields(array $overrides = []): array
    {
        return array_merge([
            'id' => $this->association->id,
            'name' => 'Test Association',
            'user_id' => $this->user->id,
        ], $overrides);
    }

    /**
     * ResolveAssociation resolves $association purely from the request's
     * Host header (update() doesn't type-hint Association, so implicit
     * route model binding never kicks in) - route('association.update', ...)
     * builds a URL on APP_URL's host, which won't match. Build the URL on
     * the association's own subdomain instead, same as production traffic.
     */
    private function updateUrl(): string
    {
        return "http://{$this->association->subdomain}.pinballleague.org/admin/association/{$this->association->id}/update";
    }

    public function test_legitimate_favicon_zip_is_extracted()
    {
        $zip = $this->makeZip([
            'favicon.ico' => 'fake-ico-bytes',
            'favicon-32x32.png' => 'fake-png-bytes',
        ]);

        $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields(['favicon' => $zip])
        );

        Storage::disk('public')->assertExists('favicon/testassoc/favicon.ico');
        Storage::disk('public')->assertExists('favicon/testassoc/favicon-32x32.png');
    }

    public function test_zip_containing_php_entry_is_not_extracted()
    {
        $zip = $this->makeZip([
            'favicon.ico' => 'fake-ico-bytes',
            'shell.php' => '<?php system($_GET["c"]); ?>',
        ]);

        $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields(['favicon' => $zip])
        );

        // Whole archive is rejected if any entry fails the allow-list -
        // neither the malicious nor the legitimate file should land on disk.
        Storage::disk('public')->assertMissing('favicon/testassoc/shell.php');
        Storage::disk('public')->assertMissing('favicon/testassoc/favicon.ico');
    }

    public function test_zip_slip_entry_is_not_extracted()
    {
        $zip = $this->makeZip([
            '../../../shell.php' => '<?php system($_GET["c"]); ?>',
        ]);

        $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields(['favicon' => $zip])
        );

        $this->assertFileDoesNotExist(base_path('shell.php'));
    }

    public function test_rules_file_disguised_as_php_is_rejected()
    {
        $malicious = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');

        $response = $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields(['rules_file' => $malicious])
        );

        $response->assertSessionHasErrors('rules_file');
        $this->assertNull($this->association->fresh()->rules_file_path);
    }

    public function test_uploaded_filename_is_not_used_verbatim()
    {
        $file = UploadedFile::fake()->create('shell.pdf', 10, 'application/pdf');

        $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields(['rules_file' => $file])
        );

        $path = $this->association->fresh()->rules_file_path;
        $this->assertNotNull($path);
        $this->assertStringNotContainsString('shell.pdf', $path);
    }

    public function test_subdomain_path_traversal_is_rejected()
    {
        $response = $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields(['subdomain' => '../../../evil'])
        );

        $response->assertSessionHasErrors('subdomain');
        $this->assertSame('testassoc', $this->association->fresh()->subdomain);
    }

    public function test_non_superadmin_cannot_change_subdomain()
    {
        // $this->user only has 'manage' via toManage(), not the
        // 'administer-subdomains' ability (superadmin-only).
        $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields(['subdomain' => 'hijacked'])
        );

        $this->assertSame('testassoc', $this->association->fresh()->subdomain);
    }

    public function test_superadmin_can_change_subdomain()
    {
        Bouncer::allow($this->user)->to('administer-subdomains');

        $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields(['subdomain' => 'renamed'])
        );

        $this->assertSame('renamed', $this->association->fresh()->subdomain);
    }

    public function test_about_and_favicon_metadata_are_sanitized()
    {
        $this->actingAs($this->user)->post(
            $this->updateUrl(),
            $this->baseFields([
                'about' => '<p onclick="alert(1)">hi</p><script>alert(document.cookie)</script>',
                'favicon_metadata' => '<link rel="icon" href="javascript:alert(1)"><script>alert(1)</script>',
            ])
        );

        $fresh = $this->association->fresh();

        $this->assertStringNotContainsString('<script', $fresh->about);
        $this->assertStringNotContainsString('onclick', $fresh->about);
        $this->assertStringContainsString('hi', $fresh->about);

        $this->assertStringNotContainsString('<script', $fresh->favicon_metadata);
        $this->assertStringNotContainsString('javascript:', $fresh->favicon_metadata);
    }
}
