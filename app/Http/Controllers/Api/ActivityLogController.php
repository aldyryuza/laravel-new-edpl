<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

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

    /**
     * Data tabel (Yajra, protokol DataTables server-side).
     * Filter berasal dari form halaman (category, keyword, date_from, date_to),
     * bukan kotak search bawaan DataTables.
     */
    public function datatable(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(AuditLog::CATEGORIES))],
            'keyword' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Activity::query()->with('causer');

        return DataTables::eloquent($query)
            ->whitelist(['created_at', 'log_name', 'description'])
            ->filter(fn (Builder $query) => $this->applyFilters($query, $filters))
            ->order(fn (Builder $query) => $this->applyOrder($query, $request))
            ->addColumn('category', fn (Activity $log) => $log->log_name)
            ->addColumn('category_label', fn (Activity $log) => AuditLog::CATEGORIES[$log->log_name] ?? $log->log_name)
            ->editColumn('causer', fn (Activity $log) => $log->causer instanceof User
                ? ['id' => $log->causer->id, 'nik' => $log->causer->nik, 'name' => $log->causer->name]
                : null)
            ->addColumn('subject', fn (Activity $log) => $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : null)
            // Array, bukan Collection: Yajra mengubah objek menjadi string.
            ->addColumn('changes', fn (Activity $log) => $log->attribute_changes?->toArray())
            ->editColumn('properties', fn (Activity $log) => $log->properties?->toArray())
            ->only(['id', 'created_at', 'category', 'category_label', 'event', 'description', 'causer', 'subject', 'changes', 'properties'])
            ->toJson();
    }

    /**
     * @param  Builder<Activity>  $query
     * @param  array<string, string|null>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $keyword = trim($filters['keyword'] ?? '');

        $query
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('log_name', $category))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->where('created_at', '<=', $date.' 23:59:59'))
            ->when($keyword !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('description', "%{$keyword}%")
                ->orWhere(fn ($query) => $query
                    ->where('causer_type', (new User)->getMorphClass())
                    ->whereIn('causer_id', User::query()->select('id')->search($keyword)))));
    }

    /**
     * Sort per kolom yang diizinkan; default terbaru di atas.
     *
     * @param  Builder<Activity>  $query
     */
    private function applyOrder(Builder $query, Request $request): void
    {
        $sortable = ['created_at' => 'id', 'log_name' => 'log_name', 'description' => 'description'];
        $order = $request->input('order.0', []);
        $column = $request->input('columns.'.($order['column'] ?? -1).'.name');
        $direction = ($order['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortable[$column] ?? 'id', isset($sortable[$column]) ? $direction : 'desc');
    }
}
