<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EmployerCommunication;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployerCommunicationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployerCommunication');
    }

    public function view(AuthUser $authUser, EmployerCommunication $employerCommunication): bool
    {
        return $authUser->can('View:EmployerCommunication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployerCommunication');
    }

    public function update(AuthUser $authUser, EmployerCommunication $employerCommunication): bool
    {
        return $authUser->can('Update:EmployerCommunication');
    }

    public function delete(AuthUser $authUser, EmployerCommunication $employerCommunication): bool
    {
        return $authUser->can('Delete:EmployerCommunication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EmployerCommunication');
    }

}