<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Messages;

use App\Domain\Messages\Owner\HorseMessageAttachmentStorage;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pokrywa HorseMessageAttachmentStorage — service do uploadu i streamingu
 * plików w wiadomościach Owner ↔ Stable.
 */
class HorseMessageAttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $stableTenant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->stableTenant = $this->makeStableTenant();
    }

    public function test_stores_valid_image_with_owner_uploader_role(): void
    {
        $horseId = (string) Str::ulid();
        $file = UploadedFile::fake()->image('iskra.jpg', 600, 400);

        $metadata = app(HorseMessageAttachmentStorage::class)
            ->storeUploadedFile($this->stableTenant, $horseId, $file, 'owner');

        $this->assertSame('iskra.jpg', $metadata['original_name']);
        $this->assertSame('image/jpeg', $metadata['mime']);
        $this->assertSame('owner', $metadata['uploader']);
        $this->assertStringContainsString("horse-messages/{$this->stableTenant->id}/{$horseId}/owner-", $metadata['path']);
        Storage::disk('local')->assertExists($metadata['path']);
    }

    public function test_rejects_file_larger_than_25_mb(): void
    {
        $horseId = (string) Str::ulid();
        // 26 MB file (1 MB over limit)
        $file = UploadedFile::fake()->create('huge.pdf', 26 * 1024, 'application/pdf');

        $this->expectException(ValidationException::class);
        app(HorseMessageAttachmentStorage::class)
            ->storeUploadedFile($this->stableTenant, $horseId, $file, 'owner');
    }

    public function test_rejects_unsupported_mime_type(): void
    {
        $horseId = (string) Str::ulid();
        // exe nie jest na liście allowed (image/pdf/video tylko)
        $file = UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload');

        $this->expectException(ValidationException::class);
        app(HorseMessageAttachmentStorage::class)
            ->storeUploadedFile($this->stableTenant, $horseId, $file, 'owner');
    }

    public function test_accepts_pdf_and_mp4_video(): void
    {
        $horseId = (string) Str::ulid();
        $pdf = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');
        $video = UploadedFile::fake()->create('longe.mp4', 5000, 'video/mp4');

        $storage = app(HorseMessageAttachmentStorage::class);

        $pdfMeta = $storage->storeUploadedFile($this->stableTenant, $horseId, $pdf, 'owner');
        $videoMeta = $storage->storeUploadedFile($this->stableTenant, $horseId, $video, 'owner');

        $this->assertSame('application/pdf', $pdfMeta['mime']);
        $this->assertSame('video/mp4', $videoMeta['mime']);
    }

    public function test_sanitizes_filename_special_chars(): void
    {
        $horseId = (string) Str::ulid();
        $file = UploadedFile::fake()->image('Iskra (po treningu) #1.jpg');

        $metadata = app(HorseMessageAttachmentStorage::class)
            ->storeUploadedFile($this->stableTenant, $horseId, $file, 'owner');

        // Spacje i specjalne znaki zamienione na underscore w path,
        // ale original_name zachowany
        $this->assertSame('Iskra (po treningu) #1.jpg', $metadata['original_name']);
        $this->assertStringContainsString('Iskra__po_treningu___1.jpg', $metadata['path']);
    }

    public function test_path_belongs_to_stable_check(): void
    {
        $storage = app(HorseMessageAttachmentStorage::class);
        $okPath = "horse-messages/{$this->stableTenant->id}/horseX/file.jpg";
        $badPath = 'horse-messages/OTHER_TENANT_ID/horseX/file.jpg';

        $this->assertTrue($storage->pathBelongsToStable($okPath, $this->stableTenant));
        $this->assertFalse($storage->pathBelongsToStable($badPath, $this->stableTenant));
        $this->assertFalse($storage->pathBelongsToStable('', $this->stableTenant));
    }

    public function test_stream_response_returns_file_contents(): void
    {
        $horseId = (string) Str::ulid();
        $file = UploadedFile::fake()->image('iskra.jpg');
        $metadata = app(HorseMessageAttachmentStorage::class)
            ->storeUploadedFile($this->stableTenant, $horseId, $file, 'owner');

        $response = app(HorseMessageAttachmentStorage::class)
            ->streamFromAttachment($metadata);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_stream_throws_when_file_missing(): void
    {
        $missing = ['path' => 'horse-messages/xxx/yyy/missing.jpg', 'mime' => 'image/jpeg'];

        $this->expectException(\RuntimeException::class);
        app(HorseMessageAttachmentStorage::class)->streamFromAttachment($missing);
    }

    public function test_delete_removes_file_idempotent(): void
    {
        $horseId = (string) Str::ulid();
        $file = UploadedFile::fake()->image('x.jpg');
        $metadata = app(HorseMessageAttachmentStorage::class)
            ->storeUploadedFile($this->stableTenant, $horseId, $file, 'owner');

        Storage::disk('local')->assertExists($metadata['path']);
        $storage = app(HorseMessageAttachmentStorage::class);
        $storage->delete($metadata['path']);
        Storage::disk('local')->assertMissing($metadata['path']);

        // Drugie delete jest no-op (idempotent).
        $storage->delete($metadata['path']);
        Storage::disk('local')->assertMissing($metadata['path']);
    }

    public function test_rejects_invalid_uploader_role(): void
    {
        $horseId = (string) Str::ulid();
        $file = UploadedFile::fake()->image('x.jpg');

        $this->expectException(\RuntimeException::class);
        app(HorseMessageAttachmentStorage::class)
            ->storeUploadedFile($this->stableTenant, $horseId, $file, 'admin'); // invalid
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'atst-'.$u,
            'name' => 'Attachment Storage Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'atst_'.$u,
            'db_username' => 'atst_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
