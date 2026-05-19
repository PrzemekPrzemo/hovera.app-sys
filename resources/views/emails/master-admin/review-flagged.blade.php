@component('mail::message')
# {{ __('mail/review_flagged.heading') }}

{{ __('mail/review_flagged.intro', ['transporter' => $transporterName]) }}

**{{ __('mail/review_flagged.flagged_by') }}:** {{ $flaggedByEmail }}
**{{ __('mail/review_flagged.rating') }}:** {{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', max(0, 5 - (int) $review->rating)) }} ({{ $review->rating }} / 5)
**{{ __('mail/review_flagged.submitted_at') }}:** {{ $review->submitted_at?->format('Y-m-d H:i') ?? '—' }}

@if ($review->comment)
> {{ $review->comment }}
@endif

---

**{{ __('mail/review_flagged.flag_reason') }}:**
{{ $review->flagged_reason }}

@component('mail::button', ['url' => $adminUrl])
{{ __('mail/review_flagged.cta') }}
@endcomponent

{{ __('mail/review_flagged.footnote') }}

@endcomponent
