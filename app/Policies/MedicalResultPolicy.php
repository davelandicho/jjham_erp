<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MedicalResult;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicalResultPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicalResult');
    }

    public function view(AuthUser $authUser, MedicalResult $medicalResult): bool
    {
        return $authUser->can('View:MedicalResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicalResult');
    }

    public function update(AuthUser $authUser, MedicalResult $medicalResult): bool
    {
        return $authUser->can('Update:MedicalResult');
    }

    public function delete(AuthUser $authUser, MedicalResult $medicalResult): bool
    {
        return $authUser->can('Delete:MedicalResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MedicalResult');
    }

}