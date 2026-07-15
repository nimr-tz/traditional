{{ $isRevision ? 'Revised abstract ready for review' : 'New abstract ready for review' }}

{{ $submission->title }}
Author: {{ $submission->user->name }}
Presentation: {{ ucfirst($submission->presentation_type) }}

Read and review: {{ $reviewUrl }}
