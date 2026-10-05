<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit log: hanya baca. Data log tidak bisa diubah/dihapus dari aplikasi.
 */
class ActivityLogController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:audit-log.read'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(AuditLog::CATEGORIES))],
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $logs = Activity::query()
            ->with('causer')
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('log_name', $category))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->where('created_at', '<=', $date.' 23:59:59'))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('description', "%{$search}%")
                ->orWhere(fn ($query) => $query
                    ->where('causer_type', (new User)->getMorphClass())
                    ->whereIn('causer_id', User::query()
                        ->select('id')
                        ->where(fn ($query) => $query->whereLike('name', "%{$search}%")->orWhereLike('nik', "%{$search}%"))))))
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => collect($logs->items())->map(fn (Activity $log) => [
                'id' => $log->id,
                'category' => $log->log_name,
                'category_label' => AuditLog::CATEGORIES[$log->log_name] ?? $log->log_name,
                'event' => $log->event,
                'description' => $log->description,
                'causer' => $log->causer instanceof User
                    ? ['id' => $log->causer->id, 'nik' => $log->causer->nik, 'name' => $log->causer->name]
                    : null,
                'subject' => $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : null,
                'changes' => $log->attribute_changes,
                'properties' => $log->properties,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
