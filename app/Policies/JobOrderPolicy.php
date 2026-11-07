<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JobOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobOrderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JobOrder');
    }

    public function view(AuthUser $authUser, JobOrder $jobOrder): bool
    {
        return $authUser->can('View:JobOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JobOrder');
    }

    public function update(AuthUser $authUser, JobOrder $jobOrder): bool
    {
        return $authUser->can('Update:JobOrder');
    }

    public function delete(AuthUser $authUser, JobOrder $jobOrder): bool
    {
        return $authUser->can('Delete:JobOrder');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JobOrder');
    }

}