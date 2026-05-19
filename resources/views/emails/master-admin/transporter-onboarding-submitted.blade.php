@component('mail::message')
# {{ __('mail/transporter_onboarding.heading') }}

{{ __('mail/transporter_onboarding.intro', ['name' => $tenant->name]) }}

**NIP:** {{ $tenant->tax_id ?: '—' }}
**Slug:** `{{ $tenant->slug }}`
**Email kontaktowy:** {{ data_get($tenant->settings, 'contact.phone') ? 'tel. '.data_get($tenant->settings, 'contact.phone') : '—' }}
**Dokumenty:** {{ $documentsUploaded }} / {{ $documentsRequired }}

@if ($documentsUploaded < $documentsRequired)
> ⚠️ {{ __('mail/transporter_onboarding.partial_documents') }}
@endif

@component('mail::button', ['url' => $adminUrl])
{{ __('mail/transporter_onboarding.cta') }}
@endcomponent

{{ __('mail/transporter_onboarding.footnote') }}

@endcomponent
