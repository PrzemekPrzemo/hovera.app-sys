<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Integrations\TodoistClient;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Endpoint dla in-panelowego buttona „Zgłoś błąd". Zbiera tytuł + opis +
 * opcjonalnie screenshot, wrzuca screenshot do public storage i tworzy
 * task w Todoist project Hovera (przez TodoistClient).
 *
 * Bez persistencji do DB: jedyne źródło prawdy = Todoist.
 */
class BugReportController extends Controller
{
    public function __construct(
        private readonly TodoistClient $todoist,
        private readonly TenantManager $tenants,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => 'required|in:bug,idea',
            'subject' => 'required|string|max:160',
            'description' => 'required|string|max:5000',
            'screenshot' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:5120',
            'source_url' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $tenant = $this->tenants->current();

        $screenshotUrl = null;
        if ($request->hasFile('screenshot')) {
            try {
                $file = $request->file('screenshot');
                $filename = sprintf(
                    'bug-reports/%s/%s.%s',
                    now()->format('Y-m'),
                    (string) Str::uuid(),
                    $file->getClientOriginalExtension(),
                );
                $path = $file->storeAs('', $filename, 'public');
                $screenshotUrl = $path !== false ? Storage::disk('public')->url($filename) : null;
            } catch (Throwable $e) {
                // Storage może rzucić jeśli brak symlinka albo brak praw zapisu.
                // Logujemy, ale task w Todoist i tak wystawiamy — bez screena.
                Log::warning('Bug report screenshot upload failed', [
                    'error' => $e->getMessage(),
                ]);
                $screenshotUrl = null;
            }
        }

        $emoji = $data['kind'] === 'bug' ? '🐛' : '💡';
        $title = sprintf('%s %s', $emoji, trim($data['subject']));

        $descLines = [
            '**Zgłoszone z aplikacji Hovera**',
            '',
            $data['description'],
            '',
            '---',
            sprintf('- Typ: %s', $data['kind']),
            sprintf('- URL: %s', $data['source_url'] ?? '—'),
            sprintf('- Tenant: %s', $tenant?->slug ?? '—'),
            sprintf('- Użytkownik: %s (%s)', $user?->email ?? '—', $user?->id ?? '—'),
            sprintf('- User-Agent: %s', substr((string) $request->userAgent(), 0, 200)),
            sprintf('- Locale: %s', app()->getLocale()),
            sprintf('- Wysłane: %s', now()->toIso8601String()),
        ];

        if ($screenshotUrl !== null) {
            $descLines[] = '';
            $descLines[] = sprintf('Załącznik: %s', $screenshotUrl);
        }

        $description = implode("\n", $descLines);

        if (! $this->todoist->isConfigured()) {
            Log::warning('Bug report submitted but TODOIST_API_TOKEN missing', [
                'title' => $title,
                'screenshot' => $screenshotUrl,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'integration_not_configured',
                'message' => 'TODOIST_API_TOKEN nie jest skonfigurowany na serwerze. Dodaj go do .env i wyczyść cache configu.',
            ], 503);
        }

        try {
            $taskId = $this->todoist->createTask(
                title: $title,
                description: $description,
                labels: [$data['kind'] === 'bug' ? 'bug' : 'idea', 'hovera-app'],
                priority: $data['kind'] === 'bug' ? 'p2' : 'p3',
            );
        } catch (Throwable $e) {
            Log::warning('Bug report → Todoist failed', [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'todoist_failed',
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'task_id' => $taskId,
        ]);
    }
}
