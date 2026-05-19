@component('mail::message')
# {{ __('mail/transport_lead_access.heading') }}

{{ __('mail/transport_lead_access.intro', ['name' => $lead->originator_name ?: '']) }}

**{{ __('mail/transport_lead_access.lead_from') }}:** {{ $lead->pickup_address }}
**{{ __('mail/transport_lead_access.lead_to') }}:** {{ $lead->dropoff_address }}
**{{ __('mail/transport_lead_access.lead_date') }}:** {{ optional($lead->preferred_date)->toDateString() ?: '—' }}
**{{ __('mail/transport_lead_access.lead_horses') }}:** {{ $lead->horse_count }}

@component('mail::button', ['url' => $portalUrl])
{{ __('mail/transport_lead_access.cta') }}
@endcomponent

{{ __('mail/transport_lead_access.permanent_link_hint') }}

{{ __('mail/transport_lead_access.signup_hint') }}

{{ __('mail/transport_lead_access.footer') }}
@endcomponent
