<?php

namespace Modules\AcademicYears\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\AcademicYears\Models\AcademicYear;

class AcademicYearController extends Controller
{
    public function index()
    {
        return AcademicYear::orderByDesc('date_start')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:academic_years,code'],
            'label' => ['required', 'string', 'max:60'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after:date_start'],
            'is_active' => ['boolean'],
        ]);

        return DB::transaction(function () use ($data) {
            if (! empty($data['is_active'])) {
                AcademicYear::where('is_active', true)->update(['is_active' => false]);
            }

            return AcademicYear::create($data);
        });
    }

    public function activate(AcademicYear $academicYear)
    {
        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return $academicYear->fresh();
    }
}
