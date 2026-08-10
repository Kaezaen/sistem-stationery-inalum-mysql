<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\DepartmentPolicy;
use App\Modules\Identity\Policies\UserPolicy;
use App\Shared\Providers\ModuleServiceProvider;

class IdentityServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'identity';
    }

    /** @return array<class-string, class-string> */
    protected function policies(): array
    {
        return [
            User::class => UserPolicy::class,
            Department::class => DepartmentPolicy::class,
        ];
    }
}
