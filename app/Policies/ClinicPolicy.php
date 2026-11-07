<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Clinic;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClinicPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Clinic');
    }

    public function view(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('View:Clinic');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Clinic');
    }

    public function update(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('Update:Clinic');
    }

    public function delete(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('Delete:Clinic');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Clinic');
    }

}