<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Department;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Department
    {
        return DB::transaction(fn (): Department => Department::create($data));
    }

    /** @param array<string, mixed> $data */
    public function update(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data): Department {
            if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
                $this->guardAgainstHierarchyCycle($department, (int) $data['parent_id']);
            }

            $department->update($data);

            return $department->refresh();
        });
    }

    /**
     * Departemen tidak boleh dihapus bila masih menaungi user atau sub-departemen.
     *
     * Penghapusan permanen akan memutus referensi historis pada request lama —
     * bertentangan dengan tuntutan pencatatan historis Bab 1 blueprint.
     */
    public function delete(Department $department): void
    {
        if ($department->users()->exists()) {
            throw new BusinessRuleException(
                'Departemen masih memiliki user. Pindahkan user terlebih dahulu sebelum menghapus.',
            );
        }

        if ($department->children()->exists()) {
            throw new BusinessRuleException(
                'Departemen masih memiliki sub-departemen. Hapus atau pindahkan sub-departemen terlebih dahulu.',
            );
        }

        $department->delete();
    }

    /** @throws BusinessRuleException */
    public function guardAgainstHierarchyCycle(Department $department, int $parentId): void
    {
        if ($parentId === $department->id) {
            throw new BusinessRuleException('Departemen tidak boleh menjadi induk dirinya sendiri.');
        }

        $seen = [$department->id => true];
        $currentId = $parentId;
        $depth = 0;

        while ($currentId !== null && $depth < 50) {
            if (isset($seen[$currentId])) {
                throw new BusinessRuleException(
                    'Penetapan induk ini membentuk siklus pada hierarki departemen.',
                );
            }

            $seen[$currentId] = true;

            $parent = Department::query()->select(['id', 'parent_id'])->find($currentId);

            if ($parent === null) {
                return;
            }

            $currentId = $parent->parent_id;
            $depth++;
        }
    }
}
