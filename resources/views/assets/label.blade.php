{{-- Printable label: rendered to a standalone SVG by AssetController::label(). --}}
<svg xmlns="http://www.w3.org/2000/svg" width="360" height="420" viewBox="0 0 360 420" font-family="Helvetica, Arial, sans-serif">
    <rect width="360" height="420" fill="#ffffff" stroke="#0f172a" stroke-width="2" rx="12"/>
    <g transform="translate(30 24)">{!! $qrSvg !!}</g>
    <text x="180" y="356" text-anchor="middle" font-size="22" font-weight="700" fill="#0f172a">{{ $asset->code }}</text>
    <text x="180" y="384" text-anchor="middle" font-size="14" fill="#334155">{{ \Illuminate\Support\Str::limit($asset->name, 40) }}</text>
    <text x="180" y="404" text-anchor="middle" font-size="11" fill="#64748b">Scan to report a problem</text>
</svg>
