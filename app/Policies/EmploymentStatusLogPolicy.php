<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EmploymentStatusLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmploymentStatusLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmploymentStatusLog');
    }

    public function view(AuthUser $authUser, EmploymentStatusLog $employmentStatusLog): bool
    {
        return $authUser->can('View:EmploymentStatusLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmploymentStatusLog');
    }

    public function update(AuthUser $authUser, EmploymentStatusLog $employmentStatusLog): bool
    {
        return $authUser->can('Update:EmploymentStatusLog');
    }

    public function delete(AuthUser $authUser, EmploymentStatusLog $employmentStatusLog): bool
    {
        return $authUser->can('Delete:EmploymentStatusLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EmploymentStatusLog');
    }

}