<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cienki klient Todoist REST API v2 — używany przez in-panelowy
 * reporter błędów do wystawiania zadań w projekcie Hovera.
 *
 * Nie persistujemy raportów do DB: jedyne źródło prawdy to Todoist.
 * Failure mode: rzucamy wyjątek; kontroler sam decyduje czy zalogować
 * i pokazać użytkownikowi błąd.
 */
class TodoistClient
{
    private const API = 'https://api.todoist.com/rest/v2';

    public function __construct(
        private readonly ?string $token,
        private readonly string $projectId,
    ) {}

    public function isConfigured(): bool
    {
        return $this->token !== null && $this->token !== '';
    }

    /**
     * Tworzy task w projekcie Hovera i zwraca jego id.
     *
     * @param  array<string,string>  $labels  Todoist labels do dodania (np. ["bug","app"])
     */
    public function createTask(string $title, string $description, array $labels = [], string $priority = 'p2'): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Todoist token not configured.');
        }

        // p1 (Todoist API "4") = highest. Wewnętrznie używamy konwencji
        // p1..p4 jak Todoist UI, mapowanie jest niezbędne bo REST używa
        // odwrotnej numeracji.
        $apiPriority = match ($priority) {
            'p1' => 4,
            'p2' => 3,
            'p3' => 2,
            default => 1,
        };

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->asJson()
                ->timeout(8)
                ->post(self::API.'/tasks', [
                    'content' => $title,
                    'description' => $description,
                    'project_id' => $this->projectId,
                    'priority' => $apiPriority,
                    'labels' => $labels,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Todoist task create failed (connection)', ['error' => $e->getMessage()]);
            throw new RuntimeException('Could not reach Todoist.', previous: $e);
        }

        if (! $response->successful()) {
            $body = (string) $response->body();
            Log::warning('Todoist task create failed', [
                'status' => $response->status(),
                'body' => $body,
            ]);
            throw new RuntimeException(sprintf(
                'Todoist API %d: %s',
                $response->status(),
                substr($body, 0, 2000),
            ));
        }

        return (string) ($response->json('id') ?? '');
    }
}
