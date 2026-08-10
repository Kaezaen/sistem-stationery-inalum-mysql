<?php

declare(strict_types=1);

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Support\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Mengaudit kejadian autentikasi (§8.2 — keamanan): login berhasil, gagal, logout.
 *
 * Kredensial gagal dicatat TANPA password — hanya identifier yang dimasukkan,
 * agar jejak audit tidak pernah menyimpan rahasia.
 */
class AuthAuditSubscriber
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        $this->logger->record('login', $user, null, null, $this->keyOf($event->user));
    }

    public function handleFailed(Failed $event): void
    {
        // Password TIDAK PERNAH dicatat.
        $this->logger->record('login_failed', null, null, [
            'credentials' => Arr::except($event->credentials, ['password']),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        $this->logger->record('logout', $user, null, null, $this->keyOf($event->user));
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
        ];
    }

    private function keyOf(mixed $user): ?int
    {
        return $user instanceof Model ? (int) $user->getKey() : null;
    }
}
