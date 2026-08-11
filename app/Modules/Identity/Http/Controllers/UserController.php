<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\UserPosition;
use App\Modules\Identity\Exceptions\ManagerCycleException;
use App\Modules\Identity\Http\Requests\StoreUserRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['department:id,code,name', 'manager:id,name'])
            ->when(
                $request->string('search')->toString() !== '',
                function ($q) use ($request): void {
                    $term = '%'.$request->string('search')->toString().'%';
                    $q->where(function ($sub) use ($term): void {
                        $sub->where('name', 'like', $term)
                            ->orWhere('employee_id', 'like', $term)
                            ->orWhere('username', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
                },
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => PaginatedPayload::make($users, fn (User $user): array => [
                'id' => $user->id,
                'employee_id' => $user->employee_id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department?->name,
                'manager' => $user->manager?->name,
                'position' => $user->position,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames()->all(),
            ]),
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', $this->formOptions());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->users->create($data, $data['roles'] ?? []);
        } catch (ManagerCycleException $e) {
            throw $e->toValidationException('manager_id');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil dibuat.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Admin/Users/Edit', [
            ...$this->formOptions($user),
            'user' => [
                'id' => $user->id,
                'employee_id' => $user->employee_id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'position' => $user->position,
                'manager_id' => $user->manager_id,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames()->all(),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Membedakan "roles tidak dikirim" (jangan sentuh) dari "roles dikirim
        // kosong" (cabut seluruh role fungsional). Memakai ?? [] akan menyamakan
        // keduanya dan diam-diam mencabut role saat field tidak ikut terkirim.
        $roles = array_key_exists('roles', $data) ? $data['roles'] : null;

        try {
            $this->users->update($user, $data, $roles);
        } catch (ManagerCycleException $e) {
            throw $e->toValidationException('manager_id');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil dinonaktifkan.');
    }

    /**
     * Visualisasi hierarki atasan — mitigasi risiko K1 pada roadmap.
     *
     * Kesalahan pemetaan manager_id membuat seluruh approval Level 1 salah sasaran
     * dan memblokir alur request. Layar ini membuat kesalahan tersebut terlihat
     * sebelum sistem dipakai, bukan setelah request pertama mandek.
     */
    public function hierarchy(): Response
    {
        $this->authorize('viewHierarchy', User::class);

        $users = User::query()
            ->with('department:id,code')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'position', 'manager_id', 'department_id', 'is_active']);

        $nodes = $users->map(fn (User $u): array => [
            'id' => $u->id,
            'name' => $u->name,
            'employee_id' => $u->employee_id,
            'position' => $u->position,
            'department' => $u->department?->code,
            'manager_id' => $u->manager_id,
            'is_active' => $u->is_active,
        ])->all();

        return Inertia::render('Admin/Users/Hierarchy', [
            'nodes' => $nodes,
            'orphans' => $users
                ->filter(fn (User $u): bool => $u->manager_id === null)
                ->pluck('name')
                ->values()
                ->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function formOptions(?User $exclude = null): array
    {
        return [
            'departments' => Department::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->all(),
            'managers' => $this->users->managerCandidates($exclude),
            'positions' => UserPosition::options(),
            'availableRoles' => array_map(
                static fn (Role $r): array => [
                    'value' => $r->value,
                    'label' => $r->label(),
                    'description' => $r->description(),
                ],
                Role::cases(),
            ),
        ];
    }
}
