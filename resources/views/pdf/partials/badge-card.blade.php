@php
    /**
     * One badge, stamped on top of the artwork.
     *
     * Shared by pdf/badge.blade.php (a single card, one per page) and
     * pdf/badges.blade.php (a batch, one card per page) so the two cannot drift
     * on what a badge looks like. Everything specific to the page — the <head>,
     * the <style>, the paper size — stays in those parents; this file is only
     * the card itself and the maths that positions text on it.
     *
     * dompdf has no flexbox and renders synthetic bold badly, so text is
     * absolutely positioned by percentage and "bolded" by drawing the same
     * string a few sub-millimetre offsets around itself. Geometry lives in
     * config/badge.php so re-skinning is an image swap, not a rewrite.
     */
    $config = config('badge');
    $place = $config['placeholders'];

    $backgroundPath = public_path($config['template']['background']);
    $background = is_file($backgroundPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($backgroundPath))
        : null;

    $tidy = fn (?string $value) => preg_replace('/\s+/', ' ', trim((string) $value));

    $badgeName = $tidy($name);
    $badgeInstitution = $tidy($institution);

    // Long names shrink rather than spilling into the institution line.
    $sizeFor = function (array $spec, string $text) {
        $size = $spec['font_size'];

        foreach ($spec['shrink_at'] ?? [] as $threshold => $smaller) {
            if (mb_strlen($text) > $threshold) {
                $size = $smaller;
            }
        }

        return $size;
    };

    /*
     * Text blocks are anchored by their BOTTOM edge, not their top: each one
     * sits on a dotted rule drawn into the artwork. Anchoring the bottom keeps
     * the text on the rule at every font size, and makes a value that wraps
     * anyway grow upwards into empty space rather than down through the rule.
     */
    /*
     * The institution may never be set in larger type than the name.
     *
     * The two shrink independently — the name to stay on one line, the
     * institution to fit two — so a long name paired with a short institution
     * would otherwise leave the institution the biggest thing on the badge. The
     * name is what a steward reads first, and it must look like it.
     */
    $mm = fn (string $value) => (float) $value;

    $capToName = function (string $size, string $nameSize) use ($mm) {
        return $mm($size) > $mm($nameSize) ? $nameSize : $size;
    };

    $style = function (array $spec, ?string $fontSize = null) {
        $rules = [
            'left: '.$spec['left'],
            isset($spec['bottom']) ? 'bottom: '.$spec['bottom'] : 'top: '.$spec['top'],
            'width: '.$spec['width'],
            'font-size: '.($fontSize ?? $spec['font_size'] ?? '3mm'),
            'line-height: '.($spec['line_height'] ?? '1.2'),
            'letter-spacing: '.($spec['letter_spacing'] ?? '0'),
            'color: '.($spec['color'] ?? '#000000'),
            'text-align: '.($spec['align'] ?? 'center'),
            'text-transform: '.($spec['transform'] ?? 'none'),
        ];

        return implode('; ', $rules).';';
    };

    /** The offsets that fake a bold weight dompdf will not render. */
    $edges = function (array $spec) {
        $offset = $spec['edge_offset_mm'] ?? 0;

        return $offset > 0
            ? [[-$offset, 0], [$offset, 0], [0, -$offset], [0, $offset]]
            : [];
    };
@endphp
<div class="badge">
    @if ($background)
        <img src="{{ $background }}" alt="" class="badge-background">
    @else
        <div class="badge-plain"></div>
        <div class="badge-plain-title">{{ $conferenceName }} {{ $conferenceYear }}</div>
    @endif

    @php $nameSize = $sizeFor($place['name'], $badgeName); @endphp
    @foreach ($edges($place['name']) as [$x, $y])
        <div class="stamp" style="{{ $style($place['name'], $nameSize) }} margin-left: {{ $x }}mm; margin-top: {{ $y }}mm;">{{ $badgeName }}</div>
    @endforeach
    <div class="stamp" style="{{ $style($place['name'], $nameSize) }}">{{ $badgeName }}</div>

    @if ($badgeInstitution)
        @php $institutionSize = $capToName($sizeFor($place['institution'], $badgeInstitution), $nameSize); @endphp
        @foreach ($edges($place['institution']) as [$x, $y])
            <div class="stamp" style="{{ $style($place['institution'], $institutionSize) }} margin-left: {{ $x }}mm; margin-top: {{ $y }}mm;">{{ $badgeInstitution }}</div>
        @endforeach
        <div class="stamp" style="{{ $style($place['institution'], $institutionSize) }}">{{ $badgeInstitution }}</div>
    @endif

    <div class="qr" style="left: {{ $place['qr']['left'] }}; top: {{ $place['qr']['top'] }}; width: {{ $place['qr']['width'] }};">
        <img src="{{ $qr }}" alt="">
    </div>
</div>
