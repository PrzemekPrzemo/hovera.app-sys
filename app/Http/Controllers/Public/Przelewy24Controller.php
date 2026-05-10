<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Central\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * User-facing landing po powrocie z P24. Sam status płatności
 * przychodzi async via webhook (Przelewy24WebhookController) — to
 * tylko renderuje "Płatność potwierdzona / w toku" message + redirect
 * do /app.
 *
 * P24 redirectuje TYLKO via GET (parametr `urlReturn`); status nie
 * jest signed, więc nie ufamy mu — wyświetlamy stan z naszej DB.
 */
class Przelewy24Controller extends Controller
{
    public function return(Request $request, string $invoiceId): RedirectResponse
    {
        $invoice = Invoice::query()->find($invoiceId);

        if ($invoice === null) {
            return redirect('/app')->with('status', __('admin/invoice.p24_return.unknown'));
        }

        $key = $invoice->isPaid()
            ? 'admin/invoice.p24_return.paid'
            : 'admin/invoice.p24_return.pending';

        return redirect('/app/billing')->with('status', __($key, ['number' => $invoice->number]));
    }
}
