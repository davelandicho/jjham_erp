<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Visa;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Visa');
    }

    public function view(AuthUser $authUser, Visa $visa): bool
    {
        return $authUser->can('View:Visa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Visa');
    }

    public function update(AuthUser $authUser, Visa $visa): bool
    {
        return $authUser->can('Update:Visa');
    }

    public function delete(AuthUser $authUser, Visa $visa): bool
    {
        return $authUser->can('Delete:Visa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Visa');
    }

}