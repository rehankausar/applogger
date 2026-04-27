@php
    $routeName = config('applogger.routes.name', 'applogger.');
@endphp

@extends(config('applogger.layout.value', 'layouts.app'))
@section('content')

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0"><i class="fas fa-file-alt"></i> Log Files</h4>
            <div>
                <a href="{{ route($routeName.'index') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-database"></i> Database Logs
                </a>
                <button type="button" class="btn btn-sm btn-danger" onclick="clearAllLogs()">
                    <i class="fas fa-trash-alt"></i> Clear All Logs
                </button>
            </div>
        </div>

        <div class="card shadow border border-light">
            <div class="card-header bg-light">
                <h6 class="mb-0 text-dark"><i class="fas fa-list"></i> Available Log Files</h6>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="40%">File Name</th>
                                <th width="15%">Size</th>
                                <th width="20%">Last Modified</th>
                                <th width="25%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logFiles as $file)
                                <tr>
                                    <td><i class="fas fa-file-alt text-muted"></i> {{ $file['name'] }}</td>
                                    <td>{{ $file['size'] }}</td>
                                    <td>{{ $file['modified'] }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="viewLogFile('{{ $file['name'] }}')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="downloadLogFile('{{ $file['name'] }}')">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                        @if($file['name'] !== 'laravel.log')
                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteLogFile('{{ $file['name'] }}')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        <i class="fas fa-inbox"></i> No log files found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Viewer Modal -->
    <div class="modal fade" id="logViewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-file-code"></i> <span id="log_file_name"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom">
                        <label class="form-label small">Lines to show:</label>
                        <select id="lines_count" class="form-select form-select-sm d-inline-block w-auto">
                            <option value="50">50 lines</option>
                            <option value="100" selected>100 lines</option>
                            <option value="200">200 lines</option>
                            <option value="500">500 lines</option>
                            <option value="1000">1000 lines</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" onclick="reloadLogContent()">
                            <i class="fas fa-sync"></i> Reload
                        </button>
                    </div>
                    <pre id="log_content" class="bg-dark text-light p-3 m-0"
                         style="max-height: 600px; overflow-y: auto; font-size: 12px; font-family: 'Courier New', monospace;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('script_page')
    <script>
        let currentFileName = '';

        function viewLogFile(fileName) {
            currentFileName = fileName;
            $('#log_file_name').text(fileName);
            loadLogContent(fileName);
            $('#logViewerModal').modal('show');
        }

        function loadLogContent(fileName, lines = 100) {
            $.ajax({
                url: '{{ route($routeName.'files.view') }}',
                method: 'GET',
                data: {file: fileName, lines: lines},
                success: function(response) {
                    if (response.success) {
                        $('#log_content').text(response.content || 'Log file is empty');
                    }
                },
                error: function() {
                    $('#log_content').text('Failed to load log file');
                }
            });
        }

        function reloadLogContent() {
            loadLogContent(currentFileName, $('#lines_count').val());
        }

        function downloadLogFile(fileName) {
            window.location.href = '{{ route($routeName.'files.download') }}?file=' + fileName;
        }

        function deleteLogFile(fileName) {
            Swal.fire({
                title: 'Delete Log File?',
                text: `Are you sure you want to delete ${fileName}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route($routeName.'files.delete') }}',
                        method: 'POST',
                        data: {file: fileName, _token: '{{ csrf_token() }}'},
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Deleted!', response.message, 'success');
                                location.reload();
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error!', 'Failed to delete log file', 'error');
                        }
                    });
                }
            });
        }

        function clearAllLogs() {
            Swal.fire({
                title: 'Clear All Log Files?',
                text: 'This will delete all log files except laravel.log (which will be cleared)',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, clear all!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route($routeName.'files.clear-all') }}',
                        method: 'POST',
                        data: {_token: '{{ csrf_token() }}'},
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Cleared!', response.message, 'success');
                                location.reload();
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error!', 'Failed to clear log files', 'error');
                        }
                    });
                }
            });
        }
    </script>
    @endpush

@endsection
