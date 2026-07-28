{{ $isRevision ? 'Abstract ready for re-review' : 'New abstract ready for review' }}
@if($isRevision)

An abstract you previously reviewed has been revised. Your earlier recommendation and comments are shown alongside it.
@endif

{{-- Blind review: never name the author here. --}}
{{ $submission->title }}
Sub-theme: {{ $submission->subtheme?->title ?? 'No sub-theme' }}
Presentation: {{ ucfirst($submission->presentation_type) }}

Read and review: {{ $reviewUrl }}
