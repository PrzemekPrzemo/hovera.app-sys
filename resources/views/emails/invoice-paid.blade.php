@component('mail::message')
# {{ __('billing.email.invoice_paid.heading', ['number' => $invoice->number]) }}

{{ __('billing.email.invoice_paid.intro', ['stable' => $tenantName]) }}

| {{ __('billing.email.invoice_paid.field_number') }} | **{{ $invoice->number }}** |
|:----|:----|
| {{ __('billing.email.invoice_paid.field_plan') }}   | {{ strtoupper($invoice->plan_code) }} |
| {{ __('billing.email.invoice_paid.field_period') }} | {{ __('billing.period.'.$invoice->period) }} |
| {{ __('billing.email.invoice_paid.field_total') }}  | **{{ $totalFormatted }}** |
| {{ __('billing.email.invoice_paid.field_paid_at') }}| {{ optional($invoice->paid_at)->format('Y-m-d H:i') }} |

{{ __('billing.email.invoice_paid.pdf_pending') }}

@component('mail::button', ['url' => url('/app/billing')])
{{ __('billing.email.invoice_paid.cta_billing') }}
@endcomponent

{{ __('billing.email.invoice_paid.thanks') }}

{{ __('billing.email.invoice_paid.signoff') }}<br>
hovera
@endcomponent
