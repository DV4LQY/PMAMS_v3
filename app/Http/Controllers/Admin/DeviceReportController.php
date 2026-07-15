<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PreventiveMaintenanceReportExport;
use App\Http\Controllers\Controller;
use App\Models\Office;
use Maatwebsite\Excel\Facades\Excel;

class DeviceReportController extends Controller
{
    /**
     * Export the global preventive maintenance report.
     */
    public function export()
    {
        $filename = 'preventive-maintenance-report-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new PreventiveMaintenanceReportExport, $filename);
    }

    /**
     * Export the preventive maintenance report filtered by office.
     */
    public function exportByOffice(Office $office)
    {
        $safeOfficeName = str($office->name)
            ->lower()
            ->replace(' ', '-')
            ->replace('/', '-');

        $filename = 'preventive-maintenance-report-'
            . $safeOfficeName . '-'
            . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new PreventiveMaintenanceReportExport($office), $filename);
    }
}