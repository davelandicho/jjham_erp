<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Contract;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContractPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Contract');
    }

    public function view(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('View:Contract');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Contract');
    }

    public function update(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('Update:Contract');
    }

    public function delete(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('Delete:Contract');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Contract');
    }

}