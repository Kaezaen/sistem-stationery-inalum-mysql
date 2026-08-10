<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\StoreDepartmentRequest;
use App\Modules\Identity\Http\Requests\UpdateDepartmentRequest;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Services\DepartmentService;
use App\Shared\Exceptions\BusinessRuleException;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $departments) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->with(['parent:id,name', 'head:id,name'])
            ->withCount('users')
            ->orderBy('code')
            ->get()
            ->map(fn (Department $d): array => [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->name,
                'account_code' => $d->account_code,
                'parent' => $d->parent?->name,
                'head' => $d->head?->name,
                'users_count' => $d->users_count,
                'is_active' => $d->is_active,
            ])
            ->all();

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Department::class);

        return Inertia::render('Admin/Departments/Create', $this->formOptions());
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->departments->create($request->validated());

        return redirect()
            ->route('admin.departments.index')
            ->with('success', 'Departemen berhasil dibuat.');
    }

    public function edit(Department $department): Response
    {
        $this->authorize('update', $department);

        return Inertia::render('Admin/Departments/Edit', [
            ...$this->formOptions($department),
            'department' => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'account_code' => $department->account_code,
                'parent_id' => $department->parent_id,
                'head_user_id' => $department->head_user_id,
                'is_active' => $department->is_active,
            ],
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        try {
            $this->departments->update($department, $request->validated());
        } catch (BusinessRuleException $e) {
            throw $e->toValidationException('parent_id');
        }

        return redirect()
            ->route('admin.departments.index')
            ->with('success', 'Departemen berhasil diperbarui.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        try {
            $this->departments->delete($department);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.departments.index')
            ->with('success', 'Departemen berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function formOptions(?Department $exclude = null): array
    {
        return [
            'parents' => Department::query()
                ->when($exclude !== null, fn ($q) => $q->whereKeyNot($exclude->id))
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->all(),
        ];
    }
}
