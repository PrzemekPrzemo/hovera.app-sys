<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Files;

use App\Filament\App\Resources\HorseResource\RelationManagers\DocumentsRelationManager;
use App\Filament\App\Resources\HorseResource\RelationManagers\PhotosRelationManager;
use ReflectionClass;
use Tests\TestCase;

/**
 * Pokrywa Faza 5 PR 5.4 — stable side badge "od ownera" w istniejącym
 * Filament HorseResource. Reflective check że Photos i Documents
 * RelationManagery deklarują uploaded_by_role w table columns/filters.
 *
 * Pełne Livewire rendering testy są intensywne i wymagają fully-booted
 * tenant context — tutaj sprawdzamy że kod jest na miejscu poprzez
 * static analysis source code'u.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5 PR 5.4".
 */
class StableUploaderBadgeTest extends TestCase
{
    public function test_photos_relation_manager_declares_uploaded_by_role_badge(): void
    {
        $source = file_get_contents((new ReflectionClass(PhotosRelationManager::class))->getFileName());
        $this->assertNotFalse($source);

        // Sprawdzamy że PhotosRelationManager zawiera BadgeColumn dla
        // uploaded_by_role + 2 colors (stable/client).
        $this->assertStringContainsString("BadgeColumn::make('uploaded_by_role')", $source);
        $this->assertStringContainsString("'primary' => 'stable'", $source);
        $this->assertStringContainsString("'warning' => 'client'", $source);
    }

    public function test_photos_relation_manager_declares_uploaded_by_filter(): void
    {
        $source = file_get_contents((new ReflectionClass(PhotosRelationManager::class))->getFileName());
        $this->assertNotFalse($source);

        // SelectFilter z opcjami stable/client
        $this->assertStringContainsString("SelectFilter::make('uploaded_by_role')", $source);
        $this->assertStringContainsString("'stable' =>", $source);
        $this->assertStringContainsString("'client' =>", $source);
    }

    public function test_documents_relation_manager_already_has_badge_and_filter(): void
    {
        // Documents już miały badge + filter przed Fazą 5 PR 5.4 (z
        // wcześniejszych iteracji portal'u klienta). Sprawdzamy że nic
        // nie usunęliśmy.
        $source = file_get_contents((new ReflectionClass(DocumentsRelationManager::class))->getFileName());
        $this->assertNotFalse($source);

        $this->assertStringContainsString("BadgeColumn::make('uploaded_by_role')", $source);
        $this->assertStringContainsString("SelectFilter::make('uploaded_by_role')", $source);
    }

    public function test_i18n_keys_present_in_horse_photo_pl_and_en(): void
    {
        // Po Fazie 5 PR 5.4 dodaliśmy klucze uploaded_by.{stable,client}
        // w lang/{pl,en}/app/horse_photo.php — sprawdzamy oba locale'e.
        foreach (['pl', 'en'] as $locale) {
            app()->setLocale($locale);
            $stable = __('app/horse_photo.uploaded_by.stable');
            $client = __('app/horse_photo.uploaded_by.client');
            $col = __('app/horse_photo.table.column.uploaded_by');

            $this->assertNotSame('app/horse_photo.uploaded_by.stable', $stable, "PL/EN locale $locale: brak stable");
            $this->assertNotSame('app/horse_photo.uploaded_by.client', $client, "Locale $locale: brak client");
            $this->assertNotSame('app/horse_photo.table.column.uploaded_by', $col, "Locale $locale: brak column label");
        }
    }
}
