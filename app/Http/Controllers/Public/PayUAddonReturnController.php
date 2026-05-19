<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Central\AddonPurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Master-admin landing po powrocie z PayU dla add-on purchase. Sam
 * status pochodzi async z webhooka — tutaj tylko redirect na widok
 * purchase z flash message. Patrz docs/TRANSPORT.md §16.
 *
 * URL: /admin/payu/addon/return/{purchase_id}
 */
class PayUAddonReturnController extends Controller
{
    public function return(Request $request, string $purchaseId): RedirectResponse
    {
        $purchase = AddonPurchase::query()->find($purchaseId);
        if ($purchase === null) {
            return redirect('/admin/addon-purchases')->with('status', __('admin/addon_purchases.return.unknown'));
        }

        $key = $purchase->isPaid()
            ? 'admin/addon_purchases.return.paid'
            : 'admin/addon_purchases.return.pending';

        return redirect('/admin/addon-purchases/'.$purchase->id)->with(
            'status',
            __($key, ['code' => $purchase->addon_code]),
        );
    }
}
