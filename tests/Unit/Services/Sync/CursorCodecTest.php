<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Services\Sync\CursorCodec;
use PHPUnit\Framework\TestCase;

class CursorCodecTest extends TestCase
{
    public function test_round_trips_tenant_id_and_version(): void
    {
        $cursor = CursorCodec::encode('01HW3K000000000000000ABCDE', 173456);
        [$tenantId, $version] = CursorCodec::decode($cursor);

        $this->assertSame('01HW3K000000000000000ABCDE', $tenantId);
        $this->assertSame(173456, $version);
    }

    public function test_decode_of_empty_cursor_yields_zero_version(): void
    {
        [$tenantId, $version] = CursorCodec::decode(null);
        $this->assertSame('', $tenantId);
        $this->assertSame(0, $version);
    }

    public function test_decode_of_garbage_yields_zero_version(): void
    {
        [$tenantId, $version] = CursorCodec::decode('not-a-real-cursor!!!');
        $this->assertSame(0, $version);
    }

    public function test_encoded_cursor_is_url_safe(): void
    {
        $cursor = CursorCodec::encode(99, 1);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $cursor);
    }
}
