<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Document;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Document');
    }

    public function view(AuthUser $authUser, Document $document): bool
    {
        return $authUser->can('View:Document');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Document');
    }

    public function update(AuthUser $authUser, Document $document): bool
    {
        return $authUser->can('Update:Document');
    }

    public function delete(AuthUser $authUser, Document $document): bool
    {
        return $authUser->can('Delete:Document');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Document');
    }

}