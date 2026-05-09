<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Import\ExcelImportService;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds and serves a sample .xlsx template per entity (clients / horses).
 * Stable owners click "Pobierz szablon" in the import wizard, paste their
 * data into the file, and re-upload — guaranteed-correct headers.
 */
class ImportTemplateController extends Controller
{
    public function __invoke(string $entity): StreamedResponse|Response
    {
        if (! in_array($entity, [ExcelImportService::ENTITY_CLIENTS, ExcelImportService::ENTITY_HORSES], true)) {
            abort(404);
        }

        [$headers, $sample] = $this->templateFor($entity);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($entity === ExcelImportService::ENTITY_CLIENTS ? 'Klienci' : 'Konie');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($sample, null, 'A2');

        // Bold header row.
        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = $entity === ExcelImportService::ENTITY_CLIENTS
            ? 'hovera-klienci-szablon.xlsx'
            : 'hovera-konie-szablon.xlsx';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @return array{0: list<string>, 1: list<string|int>}
     */
    private function templateFor(string $entity): array
    {
        if ($entity === ExcelImportService::ENTITY_CLIENTS) {
            return [
                ['Imię', 'Nazwisko', 'Email', 'Telefon', 'Ulica', 'Kod pocztowy', 'Miasto', 'NIP', 'Notatki'],
                ['Anna', 'Kowalska', 'anna.kowalska@example.com', '+48 600 123 456', 'ul. Kwiatowa 5', '00-001', 'Warszawa', '', 'Klient z 2023'],
            ];
        }

        return [
            ['Imię konia', 'Rasa', 'Płeć', 'Maść', 'Data urodzenia', 'Microchip', 'Email właściciela', 'Notatki'],
            ['Bursztyn', 'Polski koń szlachetny półkrwi', 'wałach', 'gniada', '2015-06-12', '', 'anna.kowalska@example.com', ''],
        ];
    }
}
