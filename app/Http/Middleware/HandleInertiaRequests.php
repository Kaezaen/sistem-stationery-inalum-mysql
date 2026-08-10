<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data yang tersedia di SETIAP halaman Inertia.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => $this->authProps($request->user()),

            'notifications' => fn (): array => [
                'unread' => ($user = $request->user()) instanceof User
                    ? $user->unreadNotifications()->count()
                    : 0,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],

            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * Identitas dan kewenangan pengguna yang sedang login.
     *
     * Permission ikut dibagikan agar React dapat menyembunyikan menu dan tombol
     * yang tidak relevan. Ini murni kenyamanan UI — BUKAN kontrol keamanan.
     * Penegakan sesungguhnya ada di Policy sisi server (§5.2 Architecture
     * Blueprint); menyembunyikan tombol tidak menghalangi siapa pun memanggil
     * endpoint-nya secara langsung.
     *
     * @return array<string, mixed>
     */
    private function authProps(?Authenticatable $user): array
    {
        if (! $user instanceof User) {
            return [
                'user' => null,
                'roles' => [],
                'permissions' => [],
            ];
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_id' => $user->employee_id,
                'department' => $user->department?->name,
            ],
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
        ];
    }
}
