@extends('layouts.app')

@section('content')
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @elseif (session('alert'))
        <div class="alert alert-danger">{{ session('alert') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="mb-3">Upload CSV</h5>
            <form action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-auto">
                    <input type="file" name="csv" class="form-control @error('csv') is-invalid @enderror"
                        accept=".csv,text/csv">
                    @error('csv')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" type="submit">Upload & Queue</button>
                </div>
            </form>
            <div class="text-muted mt-2 small">
                Headers: UNIQUE_KEY, PRODUCT_TITLE, PRODUCT_DESCRIPTION, STYLE#, SANMAR_MAINFRAME_COLOR, SIZE, COLOR_NAME,
                PIECE_PRICE
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Recent Uploads</h5>
                <span class="text-muted small" id="last-refresh">—</span>
            </div>
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle" id="uploads-table">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Upserted</th>
                            <th>Failed</th>
                            <th>Size</th>
                            <th>Uploaded At</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($uploads as $u)
                            @php $err = $u->meta['error'] ?? null; @endphp
                            @php
                                $map = [
                                    'queued' => 'secondary',
                                    'processing' => 'warning',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'skipped' => 'info',
                                ];
                            @endphp
                            <tr data-id="{{ $u->id }}">
                                <td>{{ $u->id }}</td>
                                <td class="truncate" title="{{ $u->original_name }}">{{ $u->original_name }}</td>
                                <td>
                                    <span
                                        class="badge bg-{{ $map[$u->status] ?? 'secondary' }} status-badge">{{ $u->status }}</span>
                                    @if ($err)
                                        <div class="text-danger small mt-1 truncate" title="{{ $err }}">
                                            {{ $err }}</div>
                                    @endif
                                </td>
                                <td>{{ $u->rows_total }}</td>
                                <td>{{ $u->rows_upserted }}</td>
                                <td>{{ $u->rows_failed }}</td>
                                <td>{{ number_format($u->size_bytes / 1024, 1) }} KB</td>
                                <td>{{ $u->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $u->updated_at?->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted">Auto-refreshes every 3s.</div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const endpoint = "{{ route('uploads.poll') }}";
        const tableBody = document.querySelector('#uploads-table tbody');
        let lastSnapshot = {};

        function esc(str) {
            return (str ?? '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
        }

        function renderRow(u) {
            const statusClass = {
                'queued': 'secondary',
                'processing': 'warning',
                'completed': 'success',
                'failed': 'danger',
                'skipped': 'info'
            } [u.status] || 'secondary';
            const err = u.meta && u.meta.error ? u.meta.error : '';
            const sizeKB = (u.size_bytes / 1024);
            return `
    <tr data-id="${u.id}">
      <td>${u.id}</td>
      <td class="truncate" title="${esc(u.file)}">${esc(u.file)}</td>
      <td>
        <span class="badge bg-${statusClass} status-badge">${esc(u.status)}</span>
        ${err ? `<div class="text-danger small mt-1 truncate" title="${esc(err)}">${esc(err)}</div>` : ``}
      </td>
      <td>${u.rows.total}</td>
      <td>${u.rows.upserted}</td>
      <td>${u.rows.failed}</td>
      <td>${sizeKB.toFixed(1)} KB</td>
      <td>${u.created_at ?? ''}</td>
      <td>${u.updated_at ?? ''}</td>
    </tr>
  `;
        }

        async function poll() {
            try {
                const res = await fetch(endpoint, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const json = await res.json();
                const data = json.data || [];

                let html = '';
                let changed = false;

                data.forEach(u => {
                    html += renderRow(u);
                    const prev = lastSnapshot[u.id];
                    if (prev && prev.status !== u.status) changed = true;
                    lastSnapshot[u.id] = {
                        status: u.status
                    };
                });

                tableBody.innerHTML = html;
                document.getElementById('last-refresh').textContent = 'Last refresh: ' + new Date()
                    .toLocaleTimeString();
                if (changed) console.log('Upload status changed');
            } catch (e) {
                console.error('Poll error', e);
            }
        }
        setInterval(poll, 3000);
    </script>
@endsection
