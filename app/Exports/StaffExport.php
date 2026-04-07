<?php

namespace App\Exports;

use App\Models\Organisation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StaffExport implements FromCollection, WithHeadings
{
    protected $organisationId;

    public function __construct($organisationId)
    {
        $this->organisationId = $organisationId;
    }

    public function collection()
    {
        $organisation = Organisation::with([
            'users.department.allDepartment', 
            'users.office'
        ])->findOrFail($this->organisationId);

        return $organisation->users->map(function ($staff) {
            return [
                'First Name' => $staff->first_name,
                'Last Name'  => $staff->last_name,
                'Email'      => $staff->email,
                'Office'     => $staff->office->name ?? '-',
               'Department'  => $staff->department->allDepartment->name ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'First Name',
            'Last Name',
            'Email',
            'Office',
            'Department',
        ];
    }
}
