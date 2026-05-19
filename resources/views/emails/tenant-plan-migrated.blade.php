@component('mail::message')
# {{ __('mail/tenant_plan_migrated.heading', ['stable' => $tenantName]) }}

{{ __('mail/tenant_plan_migrated.intro') }}

| {{ __('mail/tenant_plan_migrated.field_old') }} | {{ $oldPlanName }} |
|:----|:----|
| {{ __('mail/tenant_plan_migrated.field_new') }} | **{{ $newPlanName }}** |
| {{ __('mail/tenant_plan_migrated.field_price') }} | **{{ $newPriceFormatted }}** |
| {{ __('mail/tenant_plan_migrated.field_effective') }} | {{ __('mail/tenant_plan_migrated.effective_'.$effective) }} |
| {{ __('mail/tenant_plan_migrated.field_lock_in') }} | {{ $lockInUntil->format('Y-m-d') }} |

{{ __('mail/tenant_plan_migrated.lock_in_explainer', ['date' => $lockInUntil->format('Y-m-d')]) }}

{{ __('mail/tenant_plan_migrated.disclaimer') }}

@component('mail::button', ['url' => url('/app/billing')])
{{ __('mail/tenant_plan_migrated.cta_billing') }}
@endcomponent

{{ __('mail/tenant_plan_migrated.questions') }}

{{ __('mail/tenant_plan_migrated.signoff') }}<br>
hovera
@endcomponent
