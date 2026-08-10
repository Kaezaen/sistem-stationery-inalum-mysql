<?php

declare(strict_types=1);

namespace App\Modules\Audit\Support;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Titik tunggal penulisan jejak audit teknis (§8.2).
 *
 * Ditempatkan di Support/ (bukan Services/) karena konvensi menuntut berkas
 * Services/ berakhiran "Service" (ditegakkan uji arsitektur); "Logger" lebih
 * tepat menggambarkan perannya. Menangkap pelaku (user login), IP, dan user agent
 * dari request berjalan. Untuk kejadian tanpa entitas (mis. login gagal)
 * $auditable boleh null.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $event,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'auditable_type' => $auditable !== null ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
