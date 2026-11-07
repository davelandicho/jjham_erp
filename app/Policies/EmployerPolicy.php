<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Employer;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Employer');
    }

    public function view(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('View:Employer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Employer');
    }

    public function update(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('Update:Employer');
    }

    public function delete(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('Delete:Employer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Employer');
    }

}