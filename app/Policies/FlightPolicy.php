<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Flight;
use Illuminate\Auth\Access\HandlesAuthorization;

class FlightPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Flight');
    }

    public function view(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('View:Flight');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Flight');
    }

    public function update(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('Update:Flight');
    }

    public function delete(AuthUser $authUser, Flight $flight): bool
    {
        return $authUser->can('Delete:Flight');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Flight');
    }

}