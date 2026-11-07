<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Offer;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfferPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Offer');
    }

    public function view(AuthUser $authUser, Offer $offer): bool
    {
        return $authUser->can('View:Offer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Offer');
    }

    public function update(AuthUser $authUser, Offer $offer): bool
    {
        return $authUser->can('Update:Offer');
    }

    public function delete(AuthUser $authUser, Offer $offer): bool
    {
        return $authUser->can('Delete:Offer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Offer');
    }

}