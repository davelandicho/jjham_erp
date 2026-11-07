<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Refund;
use Illuminate\Auth\Access\HandlesAuthorization;

class RefundPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Refund');
    }

    public function view(AuthUser $authUser, Refund $refund): bool
    {
        return $authUser->can('View:Refund');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Refund');
    }

    public function update(AuthUser $authUser, Refund $refund): bool
    {
        return $authUser->can('Update:Refund');
    }

    public function delete(AuthUser $authUser, Refund $refund): bool
    {
        return $authUser->can('Delete:Refund');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Refund');
    }

}