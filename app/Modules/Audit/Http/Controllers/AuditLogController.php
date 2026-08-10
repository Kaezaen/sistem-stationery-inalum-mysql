<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Enums\Permission;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Audit Log — akses Administrator saja (§8.2).
 *
 * Jejak teknis bersifat baca saja; append-only. Otorisasi cukup di tingkat
 * permission audit.view (hanya dimiliki Administrator pada matriks §5.1).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizePermission($request, Permission::AuditView->value);

        $event = $request->string('event')->toString();
        $from = $this->safeDate($request->query('from'));
        $until = $this->safeDate($request->query('until'));

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($event !== '', fn (Builder $q): Builder => $q->where('event', $event))
            ->when($from !== null, fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $from))
            ->when($until !== null, fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $until))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => PaginatedPayload::make($logs, fn (AuditLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'entity' => $log->auditable_type !== null
                    ? class_basename($log->auditable_type).' #'.$log->auditable_id
                    : null,
                'user' => $log->user?->name,
                'ip' => $log->ip_address,
                'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
                'old' => $log->old_values,
                'new' => $log->new_values,
            ]),
            'filters' => [
                'event' => $event,
                'from' => $from,
                'until' => $until,
            ],
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event')->all(),
        ]);
    }

    private function safeDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
