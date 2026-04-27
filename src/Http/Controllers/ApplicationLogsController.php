<?php

namespace FastnetKSA\AppLogger\Http\Controllers;

use Carbon\Carbon;
use FastnetKSA\AppLogger\Models\ApplicationLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yajra\DataTables\Facades\DataTables;

class ApplicationLogsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $eagerLoads = array_merge(
                ['user'],
                array_keys(config('applogger.relationships', []))
            );

            $query = ApplicationLog::with($eagerLoads)->orderBy('created_at', 'desc');

            if ($request->filled('level'))     $query->where('level', $request->level);
            if ($request->filled('type'))      $query->where('type', $request->type);
            if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
            if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);
            if ($request->filled('user_id'))   $query->where('user_id', $request->user_id);

            $table = DataTables::of($query);

            $table->addColumn('actions', function ($row) {
                return '<button type="button" class="btn btn-sm btn-info" onclick="showLogDetails('.$row->id.')">
                            <i class="fas fa-eye"></i> View
                        </button>';
            });

            $table->editColumn('level', function ($row) {
                $badges = [
                    'error'   => 'danger',
                    'warning' => 'warning',
                    'info'    => 'info',
                    'debug'   => 'secondary',
                ];
                $badge = $badges[$row->level] ?? 'secondary';
                return '<span class="badge bg-'.$badge.'">'.strtoupper($row->level).'</span>';
            });

            $table->editColumn('type', function ($row) {
                return '<span class="badge bg-primary">'.ucfirst($row->type).'</span>';
            });

            $table->editColumn('message', function ($row) {
                return '<span class="text-truncate d-inline-block" style="max-width: 300px;" title="'.e($row->message).'">'.
                    e($row->short_message).'</span>';
            });

            $table->editColumn('user_id', function ($row) {
                return $row->user ? $row->user->name : '-';
            });

            $table->editColumn('created_at', function ($row) {
                return $row->created_at->format('Y-m-d H:i:s');
            });

            $table->rawColumns(['actions', 'level', 'type', 'message']);

            return $table->make(true);
        }

        $levels = [
            ApplicationLog::LEVEL_ERROR   => 'Error',
            ApplicationLog::LEVEL_WARNING => 'Warning',
            ApplicationLog::LEVEL_INFO    => 'Info',
            ApplicationLog::LEVEL_DEBUG   => 'Debug',
        ];

        // Types come entirely from config — no hardcoded constants
        $types = config('applogger.types', []);

        $stats = [
            'total'    => ApplicationLog::count(),
            'errors'   => ApplicationLog::where('level', ApplicationLog::LEVEL_ERROR)->count(),
            'warnings' => ApplicationLog::where('level', ApplicationLog::LEVEL_WARNING)->count(),
            'today'    => ApplicationLog::whereDate('created_at', today())->count(),
        ];

        return view('applogger::logs.index', compact('levels', 'types', 'stats'));
    }

    public function show($id)
    {
        $eagerLoads = array_merge(
            ['user'],
            array_keys(config('applogger.relationships', []))
        );

        $log = ApplicationLog::with($eagerLoads)->findOrFail($id);

        $logData = [
            'id'          => $log->id,
            'level'       => $log->level,
            'type'        => $log->type,
            'message'     => $log->message,
            'context'     => $log->formatted_context,
            'stack_trace' => $log->stack_trace,
            'file'        => $log->file,
            'line'        => $log->line,
            'url'         => $log->url,
            'method'      => $log->method,
            'ip_address'  => $log->ip_address,
            'user'        => $log->user ? $log->user->name : 'N/A',
            'created_at'  => $log->created_at->format('Y-m-d H:i:s'),
        ];

        // Append dynamic relationship display values
        foreach (config('applogger.relationships', []) as $name => $definition) {
            $related     = $log->$name;
            $displayAttr = $definition['display'] ?? 'name';
            $logData[$name] = $related ? $related->$displayAttr : 'N/A';
        }

        return response()->json(['success' => true, 'log' => $logData]);
    }

    public function cleanup(Request $request)
    {
        $request->validate(['days' => 'required|integer|min:1|max:365']);

        $date  = Carbon::now()->subDays($request->days);
        $count = ApplicationLog::where('created_at', '<', $date)->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$count} log entries older than {$request->days} days.",
        ]);
    }

    public function health()
    {
        $thresholds = config('applogger.health', ['critical_errors' => 100, 'warning_errors' => 10]);

        $last24Hours = ApplicationLog::where('created_at', '>=', Carbon::now()->subDay())->count();
        $errors24h   = ApplicationLog::errors()->where('created_at', '>=', Carbon::now()->subDay())->count();
        $warnings24h = ApplicationLog::warnings()->where('created_at', '>=', Carbon::now()->subDay())->count();

        $health = [
            'status'         => $errors24h > $thresholds['critical_errors']
                ? 'critical'
                : ($errors24h > $thresholds['warning_errors'] ? 'warning' : 'healthy'),
            'total_logs_24h' => $last24Hours,
            'errors_24h'     => $errors24h,
            'warnings_24h'   => $warnings24h,
            'last_error'     => ApplicationLog::errors()->latest()->first(),
        ];

        return response()->json($health);
    }

    public function fileLogs(Request $request)
    {
        $logPath  = storage_path('logs');
        $logFiles = collect(\File::files($logPath))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => [
                'name'     => $file->getFilename(),
                'path'     => $file->getPathname(),
                'size'     => $this->formatBytes($file->getSize()),
                'modified' => Carbon::createFromTimestamp($file->getMTime())->format('Y-m-d H:i:s'),
            ])
            ->values();

        return view('applogger::logs.files', compact('logFiles'));
    }

    public function viewFile(Request $request)
    {
        $fileName = $request->input('file');
        $logPath  = storage_path('logs/'.$fileName);

        if (!file_exists($logPath) || !str_starts_with(realpath($logPath), storage_path('logs'))) {
            abort(404, 'Log file not found');
        }

        $lines   = $request->input('lines', 100);
        $content = $this->tailFile($logPath, $lines);

        return response()->json(['success' => true, 'file' => $fileName, 'content' => $content]);
    }

    public function downloadFile(Request $request)
    {
        $fileName = $request->input('file');
        $logPath  = storage_path('logs/'.$fileName);

        if (!file_exists($logPath) || !str_starts_with(realpath($logPath), storage_path('logs'))) {
            abort(404, 'Log file not found');
        }

        return response()->download($logPath);
    }

    public function deleteFile(Request $request)
    {
        $fileName = $request->input('file');
        $logPath  = storage_path('logs/'.$fileName);

        if (!file_exists($logPath) || !str_starts_with(realpath($logPath), storage_path('logs'))) {
            return response()->json(['success' => false, 'message' => 'Log file not found'], 404);
        }

        if ($fileName === 'laravel.log') {
            return response()->json(['success' => false, 'message' => 'Cannot delete main laravel.log file'], 403);
        }

        unlink($logPath);

        return response()->json(['success' => true, 'message' => 'Log file deleted successfully']);
    }

    public function clearAllFiles(Request $request)
    {
        $logPath = storage_path('logs');
        $files   = \File::files($logPath);
        $deleted = 0;

        foreach ($files as $file) {
            if ($file->getFilename() !== 'laravel.log') {
                unlink($file->getPathname());
                $deleted++;
            }
        }

        file_put_contents(storage_path('logs/laravel.log'), '');

        return response()->json([
            'success' => true,
            'message' => "Cleared {$deleted} log files and reset laravel.log",
        ]);
    }

    private function tailFile(string $file, int $lines = 100): string
    {
        $handle      = fopen($file, 'r');
        $linecounter = $lines;
        $pos         = -2;
        $beginning   = false;
        $text        = [];

        while ($linecounter > 0) {
            $t = ' ';
            while ($t !== "\n") {
                if (fseek($handle, $pos, SEEK_END) === -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }

        fclose($handle);

        return implode('', array_reverse($text));
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
