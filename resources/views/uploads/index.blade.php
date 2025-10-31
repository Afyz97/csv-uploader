@extends('layouts.app')

@section('content')
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @elseif (session('alert'))
        <div class="alert alert-danger">{{ session('alert') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="fw-semibold mb-4 text-primary">
                <i class="fa-solid fa-file-arrow-up me-2"></i> Upload CSV
            </h5>

            <form id="csv-form" action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data"
                class="d-inline">
                @csrf
                <input id="csv-input" type="file" name="csv" accept=".csv,text/csv" class="d-none">
            </form>

            <div id="dropzone" class="dz text-center mx-auto">
                <div class="dz-inner">
                    <div class="dz-icon mb-3">📄</div>
                    <div class="dz-text">
                        <div class="dz-title mb-2">Drag and drop your CSV file here</div>
                        <div class="dz-sub">
                            or <button id="browse-btn" type="button"
                                class="btn btn-sm btn-outline-primary rounded-pill px-3">Browse File</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="uploading" class="text-info small mt-3 d-none text-center">Uploading… please wait…</div>

            @error('csv')
                <div class="invalid-feedback d-block mt-2 text-center">{{ $message }}</div>
            @enderror
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
                            <th>Time</th>
                            <th>File Name</th>
                            <th>Status</th>
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
                                <td>{{ $u->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td class="truncate" title="{{ $u->original_name }}">{{ $u->original_name }}</td>
                                <td>
                                    <span
                                        class="badge bg-{{ $map[$u->status] ?? 'secondary' }} status-badge">{{ $u->status }}</span>
                                    @if ($err)
                                        <div class="text-danger small mt-1 truncate" title="{{ $err }}">
                                            {{ $err }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted">Auto-refreshes every 3s.</div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .dz {
            max-width: 500px;
            border: 2px dashed #cdd4e0;
            border-radius: 1rem;
            background: #f9fbff;
            padding: 3rem 1.5rem;
            transition: all 0.25s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            margin: 0 auto;
        }

        .dz:hover {
            border-color: #0d6efd;
            background: #f3f7ff;
            box-shadow: 0 4px 14px rgba(13, 110, 253, 0.06);
            transform: translateY(-1px);
        }

        .dz.dragover {
            border-color: #0d6efd;
            background: #e9f2ff;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15) inset;
            transform: scale(1.02);
        }

        .dz-inner {
            text-align: center;
        }

        .dz-icon {
            font-size: 3rem;
            color: #0d6efd;
            opacity: 0.9;
            line-height: 1;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-4px);
            }
        }

        .dz-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .dz-sub {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .dz-sub .btn {
            border-radius: 50px;
            font-size: 0.85rem;
            padding: 0.3rem 0.9rem;
        }

        #uploading {
            display: inline-block;
            font-style: italic;
            color: #0d6efd !important;
            animation: pulse 1.2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.4;
            }

            50% {
                opacity: 1;
            }
        }
    </style>
@endsection

@section('scripts')
    <script>
        // -------- Drag & Drop + Browse ----------
        (function() {
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('csv-input');
            const browseBtn = document.getElementById('browse-btn');
            const uploadBtn = document.getElementById('upload-btn');
            const fileNameEl = document.getElementById('file-name');
            const uploading = document.getElementById('uploading');
            const form = document.getElementById('csv-form');

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight() {
                dropzone.classList.add('dragover');
            }

            function unhighlight() {
                dropzone.classList.remove('dragover');
            }

            function setFileAndSubmit(file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                fileNameEl.textContent = file.name;
                uploading.classList.remove('d-none');
                form.submit();
            }

            browseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });

            fileInput.addEventListener('change', function() {
                if (fileInput.files && fileInput.files[0]) {
                    setFileAndSubmit(fileInput.files[0]);
                }
            });

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => {
                dropzone.addEventListener(ev, preventDefaults, false);
                document.body.addEventListener(ev, preventDefaults, false);
            });
            ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, highlight, false));
            ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, unhighlight, false));

            dropzone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                if (!dt || !dt.files || !dt.files.length) return;

                const file = dt.files[0];
                const okTypes = ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'];
                const isCsvByName = /\.csv$/i.test(file.name || '');
                const isCsvByType = okTypes.includes(file.type);

                if (!isCsvByName && !isCsvByType) {
                    alert('Please drop a CSV file (.csv).');
                    return;
                }
                setFileAndSubmit(file);
            });

            // Optional manual fallback (kept hidden)
            uploadBtn.addEventListener('click', function() {
                if (fileInput.files && fileInput.files[0]) {
                    uploading.classList.remove('d-none');
                    form.submit();
                } else {
                    fileInput.click();
                }
            });
        })();

        // -------- Polling for history ----------
        const endpoint = "{{ route('uploads.poll') }}";
        const tableBody = document.querySelector('#uploads-table tbody');
        let lastSnapshot = {};

        function esc(str) {
            return (str ?? '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
        }

        function renderRow(u) {
            const statusClass = ({
                queued: 'secondary',
                processing: 'warning',
                completed: 'success',
                failed: 'danger',
                skipped: 'info'
            })[u.status] || 'secondary';
            const err = u.meta && u.meta.error ? u.meta.error : '';
            return `
            <tr data-id="${u.id}">
              <td>${u.created_at ?? ''}</td>
              <td class="truncate" title="${esc(u.file)}">${esc(u.file)}</td>
              <td>
                <span class="badge bg-${statusClass} status-badge">${esc(u.status)}</span>
                ${err ? `<div class="text-danger small mt-1 truncate" title="${esc(err)}">${esc(err)}</div>` : ``}
              </td>
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
