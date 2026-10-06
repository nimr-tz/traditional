<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'Helvetica', sans-serif; color: #27364b; margin: 0; padding: 32px; font-size: 11px; }
    h1 { font-size: 20px; color: #2f5233; margin: 0 0 4px; }
    .meta { font-size: 9px; color: #6b7280; margin: 0 0 2px; }
    .subtheme { font-size: 14px; font-weight: bold; color: #1F4E1F; margin: 24px 0 12px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
    .subtheme:first-of-type { margin-top: 24px; }
    .summary { page-break-after: always; }
    .summary h2 { font-size: 14px; color: #1F4E1F; margin: 24px 0 8px; }
    .summary table { width: 100%; border-collapse: collapse; }
    .summary th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 4px 6px; }
    .summary td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
    .summary .count { text-align: right; width: 70px; font-variant-numeric: tabular-nums; }
    .summary .total td { font-weight: bold; border-top: 1px solid #27364b; border-bottom: 0; }
    .abstract { margin-bottom: 18px; }
    .abstract-title { font-size: 12px; font-weight: bold; margin: 0 0 4px; }
    .authors { margin: 0 0 4px; }
    .status-line { font-size: 9px; color: #444444; margin: 0 0 8px; }
    .section-label { font-weight: bold; margin: 6px 0 2px; }
    .section-body { margin: 0 0 4px; white-space: pre-wrap; }
</style>
</head>
<body>
    <h1>Traditional Medicine Scientific Conference — Abstract Submissions</h1>
    <div class="meta">Generated {{ now()->format('j F Y, H:i') }}</div>
    @if($statusLabel)
        <div class="meta">Filtered to status: {{ $statusLabel }}</div>
    @endif
    @if($search)
        <div class="meta">Filtered to search: "{{ $search }}"</div>
    @endif

    <div class="summary">
        <h2>Summary by theme</h2>
        <table>
            <thead>
                <tr><th>Theme</th><th class="count">Abstracts</th></tr>
            </thead>
            <tbody>
                @foreach($subthemes as $subtheme)
                    <tr>
                        <td>{{ $loop->iteration }}. {{ $subtheme->title }}</td>
                        <td class="count">{{ $subtheme->abstractSubmissions->count() }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    <td class="count">{{ $subthemes->sum(fn ($subtheme) => $subtheme->abstractSubmissions->count()) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @foreach($subthemes as $subtheme)
        <div class="subtheme">{{ $loop->iteration }}. {{ $subtheme->title }}
            <span class="meta">— {{ $subtheme->abstractSubmissions->count() }} {{ \Illuminate\Support\Str::plural('abstract', $subtheme->abstractSubmissions->count()) }}</span>
        </div>

        @foreach($subtheme->abstractSubmissions as $abstract)
            <div class="abstract">
                <div class="abstract-title">{{ $abstract->title }}</div>
                <div class="authors">Authors:
                    @foreach($abstract->authors as $author)
                        {{ $author['name'] ?? 'Unnamed' }}{{ ! empty($author['institution']) ? ' ('.$author['institution'].')' : '' }}{{ ! empty($author['is_presenter']) ? ' — presenting author' : '' }}{{ ! $loop->last ? '; ' : '' }}
                    @endforeach
                </div>
                <div class="status-line">
                    Presentation type: {{ ucfirst($abstract->presentation_type) }}
                    &nbsp;|&nbsp; Status: {{ $statusLabels[$abstract->status] ?? $abstract->status }}
                </div>

                @foreach(\App\Models\AbstractSubmission::SECTIONS as $sectionKey)
                    @php($text = (string) $abstract->{$sectionKey})
                    @continue($text === '')
                    <div class="section-label">{{ ucfirst($sectionKey) }}</div>
                    <div class="section-body">{{ $text }}</div>
                @endforeach
            </div>
        @endforeach
    @endforeach
</body>
</html>
