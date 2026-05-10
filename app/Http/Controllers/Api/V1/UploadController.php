<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Returns presigned PUT URLs for direct-to-S3/R2 uploads. Mobile clients
 * compute the sha256 of the file, request a URL, PUT the bytes, then
 * reference the resulting storage_key from a normal sync mutation.
 */
class UploadController
{
    public function horsePhoto(Request $request): JsonResponse
    {
        return $this->presign($request, prefix: 'horse-photos', allowedMime: ['image/jpeg', 'image/png', 'image/heic']);
    }

    public function horseDocument(Request $request): JsonResponse
    {
        return $this->presign($request, prefix: 'horse-documents', allowedMime: ['application/pdf', 'image/jpeg', 'image/png']);
    }

    private function presign(Request $request, string $prefix, array $allowedMime): JsonResponse
    {
        $data = $request->validate([
            'sha256' => ['required', 'string', 'size:64'],
            'mime' => ['required', Rule::in($allowedMime)],
            'byte_size' => ['required', 'integer', 'min:1', 'max:'.(50 * 1024 * 1024)],
        ]);

        $tenantId = $request->attributes->get('tenant')?->id ?: 'unknown';
        $key = sprintf('%s/%s/%s/%s', $prefix, $tenantId, substr($data['sha256'], 0, 2), $data['sha256']);

        $disk = config('filesystems.default');
        $config = config('filesystems.disks.'.$disk, []);

        // Soft-fail if S3 isn't configured — still return a deterministic key
            // so the client can keep its mutation queue ordered. UploadController
        // is the only place that interacts with the bucket; everything else
        // works with the storage_key.
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? env('AWS_DEFAULT_REGION', 'eu-central-1'),
            'endpoint' => $config['endpoint'] ?? env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'credentials' => [
                'key' => $config['key'] ?? env('AWS_ACCESS_KEY_ID', ''),
                'secret' => $config['secret'] ?? env('AWS_SECRET_ACCESS_KEY', ''),
            ],
        ]);

        $cmd = $s3->getCommand('PutObject', [
            'Bucket' => $config['bucket'] ?? env('AWS_BUCKET'),
            'Key' => $key,
            'ContentType' => $data['mime'],
            'ContentLength' => $data['byte_size'],
        ]);
        $req = $s3->createPresignedRequest($cmd, '+15 minutes');

        return new JsonResponse([
            'storage_key' => $key,
            'upload_url' => (string) $req->getUri(),
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => $data['mime'],
                'x-amz-meta-sha256' => $data['sha256'],
            ],
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
            'request_id' => (string) Str::uuid(),
        ]);
    }
}
