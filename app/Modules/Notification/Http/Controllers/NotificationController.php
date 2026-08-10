<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inbox notifikasi in-app — fitur 6.
 *
 * Setiap pengguna hanya melihat notifikasinya sendiri (lewat relasi Notifiable),
 * sehingga tidak perlu Policy tambahan: lingkupnya sudah dibatasi ke $request->user().
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $notifications = $user->notifications()->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => PaginatedPayload::make(
                $notifications,
                fn (DatabaseNotification $n): array => $this->present($n),
            ),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Menandai satu notifikasi terbaca lalu mengarahkan ke dokumen terkait. */
    public function read(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        $data = is_array($notification->data) ? $notification->data : [];
        $url = is_string($data['url'] ?? null) ? $data['url'] : '/notifications';

        return redirect($url);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai terbaca.');
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $n): array
    {
        $data = is_array($n->data) ? $n->data : [];

        return [
            'id' => $n->id,
            'code' => $data['code'] ?? null,
            'title' => $data['title'] ?? 'Notifikasi',
            'message' => $data['message'] ?? '',
            'reference' => $data['reference'] ?? null,
            'url' => $data['url'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->format('d/m/Y H:i'),
        ];
    }
}
