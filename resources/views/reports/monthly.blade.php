{{-- Rendered to PDF by dompdf: inline CSS only, no external assets. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $client->name }} · {{ $month->format('F Y') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 12px; margin: 18px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .muted { color: #64748b; }
        .kpis td { width: 25%; padding: 8px; border: 1px solid #e2e8f0; }
        .kpis .value { font-size: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f1f5f9; font-size: 9px; text-transform: uppercase; }
        th, td.cell { padding: 4px 5px; border-bottom: 1px solid #e2e8f0; }
        .bad { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>{{ $client->name }}: maintenance report</h1>
    <p class="muted">{{ $month->format('F Y') }} · prepared by {{ $tenant->name }} · generated {{ now()->timezone($tenant->timezone)->format('d M Y H:i T') }}</p>

    <table class="kpis">
        <tr>
            <td><div class="muted">Raised</div><div class="value">{{ $totals['raised'] }}</div></td>
            <td><div class="muted">Resolved</div><div class="value">{{ $totals['resolved'] }}</div></td>
            <td><div class="muted">Resolution SLA met</div><div class="value">{{ $totals['resolution_compliance'] === null ? '—' : $totals['resolution_compliance'].'%' }}</div></td>
            <td><div class="muted">Avg. time to resolve</div><div class="value">{{ $totals['avg_resolution_hours'] === null ? '—' : $totals['avg_resolution_hours'].' h' }}</div></td>
        </tr>
        <tr>
            <td><div class="muted">Closed</div><div class="value">{{ $totals['closed'] }}</div></td>
            <td><div class="muted">Still open</div><div class="value">{{ $totals['still_open'] }}</div></td>
            <td><div class="muted">Response SLA breached</div><div class="value {{ $totals['response_breached'] ? 'bad' : '' }}">{{ $totals['response_breached'] }}</div></td>
            <td><div class="muted">Resolution SLA breached</div><div class="value {{ $totals['resolution_breached'] ? 'bad' : '' }}">{{ $totals['resolution_breached'] }}</div></td>
        </tr>
    </table>

    <h2>By site</h2>
    <table>
        <thead><tr><th>Site</th><th>Raised</th><th>Resolved</th><th>Breached</th></tr></thead>
        <tbody>
            @forelse ($by_site as $row)
                <tr><td class="cell">{{ $row['site'] }}</td><td class="cell">{{ $row['raised'] }}</td><td class="cell">{{ $row['resolved'] }}</td><td class="cell">{{ $row['breached'] }}</td></tr>
            @empty
                <tr><td class="cell muted" colspan="4">No work orders this month.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>By category</h2>
    <table>
        <thead><tr><th>Category</th><th>Raised</th></tr></thead>
        <tbody>
            @foreach ($by_category as $row)
                <tr><td class="cell">{{ $row['category'] }}</td><td class="cell">{{ $row['raised'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Work orders</h2>
    <table>
        <thead><tr><th>Number</th><th>Raised</th><th>Site / location</th><th>Title</th><th>Priority</th><th>Status</th><th>SLA</th></tr></thead>
        <tbody>
            @foreach ($rows as $wo)
                <tr>
                    <td class="cell">{{ $wo->number }}</td>
                    <td class="cell">{{ $wo->created_at->timezone($wo->site->timezone)->format('d M H:i') }}</td>
                    <td class="cell">{{ $wo->site->name }}<br><span class="muted">{{ $wo->location->path }}</span></td>
                    <td class="cell">{{ $wo->title }}</td>
                    <td class="cell">{{ $wo->priority->label() }}</td>
                    <td class="cell">{{ $wo->status->label() }}</td>
                    <td class="cell {{ $wo->is_response_breached || $wo->is_resolution_breached ? 'bad' : '' }}">{{ $wo->is_response_breached || $wo->is_resolution_breached ? 'Breached' : 'Met' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
