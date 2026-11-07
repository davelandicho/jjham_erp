<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    private const ROLES = [
        'Admin',
        'Management',
        'Recruitment Staff',
        'Documentation Staff',
        'Deployment Staff',
        'Finance',
    ];

    private const MATRIX = [
        'User'                => ['VCE', 'V', '-',    '-',   '-',   '-'],
        'Role'                => ['VCE', 'V', '-',    '-',   '-',   '-'],
        'DocumentType'        => ['VCE', 'V', 'V',    'V',   'V',   '-'],
        'Clinic'              => ['VCE', 'V', 'V',    'VCE', '-',   '-'],
        'Employer'            => ['VCE', 'V', 'VCE',  'V',   'V',   '-'],
        'JobOrder'            => ['VCE', 'V', 'VCE',  'V',   'V',   'V'],
        'Applicant'           => ['V',   'V', 'VCE',  'VCE', 'V',   '-'],
        'Application'         => ['V',   'V', 'VCE',  'V',   'V',   '-'],
        'Document'            => ['V',   '-', 'V',    'VCE', 'VC',  '-'],
        'MedicalResult'       => ['V',   'V', 'V',    'VCE', '-',   '-'],
        'EmployerCommunication' => ['V', 'V', 'VCE',  'V',   'VCE', '-'],
        'Offer'               => ['V',   'V', 'VCE',  'V',   '-',   '-'],
        'Contract'            => ['V',   'V', 'VCE',  'V',   '-',   '-'],
        'Payment'             => ['V',   'V', '-',    '-',   'VC',  'VE'],
        'Refund'              => ['V',   'V', '-',    '-',   'V',   'VCE'],
        'Visa'                => ['V',   'V', 'V',    'VCE', 'V',   '-'],
        'ExitClearance'       => ['V',   'V', 'V',    'VCE', 'V',   '-'],
        'Flight'              => ['V',   'V', 'V',    'V',   'VCE', '-'],
        'EmploymentStatusLog' => ['V',   'V', 'V',    'V',   'VCE', '-'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('Approve:Refund', 'web');

        foreach (self::ROLES as $column => $roleName) {
            $permissions = [];

            foreach (self::MATRIX as $model => $row) {
                $letters = $row[$column];

                if (str_contains($letters, 'V')) {
                    $permissions[] = "ViewAny:{$model}";
                    $permissions[] = "View:{$model}";
                }

                if (str_contains($letters, 'C')) {
                    $permissions[] = "Create:{$model}";
                }
                if (str_contains($letters, 'E')) {
                    $permissions[] = "Update:{$model}";
                }
            }

            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        Role::findByName('Admin', 'web')->givePermissionTo('Approve:Refund');
    }
}
