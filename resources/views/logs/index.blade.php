@php
    $routeName     = config('applogger.routes.name', 'applogger.');
    $relationships = config('applogger.relationships', []);
@endphp

@extends(config('applogger.layout.value', 'layouts.app'))
@section('content')

    <div class="container-fluid">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Logs</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['total']) }}</div>
                            </div>
                            <div class="col-auto"><i class="fas fa-clipboard-list fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Errors</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['errors']) }}</div>
                            </div>
                            <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Warnings</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['warnings']) }}</div>
                            </div>
                            <div class="col-auto"><i class="fas fa-exclamation-circle fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Today</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['today']) }}</div>
                            </div>
                            <div class="col-auto"><i class="fas fa-calendar-day fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card shadow border border-light">
            <div class="card-header bg-light d-flex align-items-center justify-content-between">
                <h6 class="mb-0 text-dark"><i class="fas fa-list-alt"></i> Database Logs</h6>
                <div>
                    <a href="{{ route($routeName.'files') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-file-alt"></i> File Logs
                    </a>
                    <button type="button" class="btn btn-sm btn-warning" onclick="cleanupLogs()">
                        <i class="fas fa-broom"></i> Cleanup Old Logs
                    </button>
                </div>
            </div>
            <div class="card-body p-4">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Level</label>
                        <select id="filter_level" class="form-select form-select-sm">
                            <option value="">All Levels</option>
                            @foreach($levels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Type</label>
                        <select id="filter_type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Date From</label>
                        <input type="date" id="filter_date_from" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Date To</label>
                        <input type="date" id="filter_date_to" class="form-control form-control-sm">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover datatable" id="logsTable">
                        <thead>
                            <tr>
                                <th width="80">Level</th>
                                <th width="100">Type</th>
                                <th>Message</th>
                                <th width="120">User</th>
                                <th width="150">Created At</th>
                                <th width="80">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Details Modal -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <tbody>
                            <tr><th width="150">ID</th><td id="log_id"></td></tr>
                            <tr><th>Level</th><td id="log_level"></td></tr>
                            <tr><th>Type</th><td id="log_type"></td></tr>
                            <tr><th>Message</th><td id="log_message"></td></tr>
                            <tr><th>User</th><td id="log_user"></td></tr>
                            @foreach($relationships as $name => $definition)
                            <tr><th>{{ ucfirst($name) }}</th><td id="log_{{ $name }}"></td></tr>
                            @endforeach
                            <tr><th>URL</th><td id="log_url"></td></tr>
                            <tr><th>Method</th><td id="log_method"></td></tr>
                            <tr><th>IP Address</th><td id="log_ip"></td></tr>
                            <tr><th>File</th><td id="log_file"></td></tr>
                            <tr><th>Line</th><td id="log_line"></td></tr>
                            <tr><th>Context</th><td><pre id="log_context" class="bg-light p-2"></pre></td></tr>
                            <tr><th>Stack Trace</th><td><pre id="log_stack_trace" class="bg-light p-2" style="max-height: 400px; overflow-y: auto;"></pre></td></tr>
                            <tr><th>Created At</th><td id="log_created_at"></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('script_page')
    <script>
        $(document).ready(function() {
            var table = $('#logsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route($routeName.'index') }}',
                    data: function(d) {
                        d.level     = $('#filter_level').val();
                        d.type      = $('#filter_type').val();
                        d.date_from = $('#filter_date_from').val();
                        d.date_to   = $('#filter_date_to').val();
                    }
                },
                columns: [
                    {data: 'level',      name: 'level'},
                    {data: 'type',       name: 'type'},
                    {data: 'message',    name: 'message'},
                    {data: 'user_id',    name: 'user_id'},
                    {data: 'created_at', name: 'created_at'},
                    {data: 'actions',    name: 'actions', orderable: false, searchable: false}
                ],
                order: [[4, 'desc']]
            });

            $('#filter_level, #filter_type, #filter_date_from, #filter_date_to').change(function() {
                table.draw();
            });
        });

        function showLogDetails(logId) {
            $.ajax({
                url: '/{{ config('applogger.routes.prefix') }}/' + logId,
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        const log = response.log;
                        $('#log_id').text(log.id);
                        $('#log_level').html('<span class="badge bg-' + getLevelBadge(log.level) + '">' + log.level.toUpperCase() + '</span>');
                        $('#log_type').html('<span class="badge bg-primary">' + log.type + '</span>');
                        $('#log_message').text(log.message);
                        $('#log_user').text(log.user);
                        @foreach($relationships as $name => $definition)
                        if (log['{{ $name }}'] !== undefined) $('#log_{{ $name }}').text(log['{{ $name }}']);
                        @endforeach
                        $('#log_url').text(log.url || 'N/A');
                        $('#log_method').text(log.method || 'N/A');
                        $('#log_ip').text(log.ip_address || 'N/A');
                        $('#log_file').text(log.file || 'N/A');
                        $('#log_line').text(log.line || 'N/A');
                        $('#log_context').text(log.context || '{}');
                        $('#log_stack_trace').text(log.stack_trace || 'N/A');
                        $('#log_created_at').text(log.created_at);
                        $('#logDetailsModal').modal('show');
                    }
                }
            });
        }

        function getLevelBadge(level) {
            const badges = {error: 'danger', warning: 'warning', info: 'info', debug: 'secondary'};
            return badges[level] || 'secondary';
        }

        function cleanupLogs() {
            Swal.fire({
                title: 'Cleanup Old Logs',
                html: `<div class="form-group text-start">
                    <label for="cleanup_days" class="form-label">Delete logs older than (days):</label>
                    <input type="number" id="cleanup_days" class="form-control" value="30" min="1">
                </div>`,
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const days = document.getElementById('cleanup_days').value;
                    if (!days || days < 1) {
                        Swal.showValidationMessage('Please enter valid number of days');
                        return false;
                    }
                    return {days: days};
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route($routeName.'cleanup') }}',
                        method: 'POST',
                        data: {days: result.value.days, _token: '{{ csrf_token() }}'},
                        success: function(response) {
                            Swal.fire('Success!', response.message, 'success');
                            $('#logsTable').DataTable().ajax.reload();
                        },
                        error: function() {
                            Swal.fire('Error!', 'Failed to cleanup logs', 'error');
                        }
                    });
                }
            });
        }
    </script>
    @endpush

@endsection
