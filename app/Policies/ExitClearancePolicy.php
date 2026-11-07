<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ExitClearance;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExitClearancePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExitClearance');
    }

    public function view(AuthUser $authUser, ExitClearance $exitClearance): bool
    {
        return $authUser->can('View:ExitClearance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExitClearance');
    }

    public function update(AuthUser $authUser, ExitClearance $exitClearance): bool
    {
        return $authUser->can('Update:ExitClearance');
    }

    public function delete(AuthUser $authUser, ExitClearance $exitClearance): bool
    {
        return $authUser->can('Delete:ExitClearance');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExitClearance');
    }

}