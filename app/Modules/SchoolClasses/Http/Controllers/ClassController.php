<?php

namespace Modules\SchoolClasses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\SchoolClasses\Http\Requests\StoreClassRequest;
use Modules\SchoolClasses\Models\SchoolClass;
use Modules\SchoolClasses\Models\SchoolCycle;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        return SchoolClass::with('cycle')
            ->when($request->search, fn ($q, $s) => $q->where('label', 'like', "%{$s}%"))
            ->orderBy('label')
            ->get();
    }

    public function store(StoreClassRequest $request)
    {
        return SchoolClass::create($request->validated() + ['school_id' => 1]);
    }

    public function show(SchoolClass $class)
    {
        return $class->load('cycle', 'installments');
    }

    public function update(StoreClassRequest $request, SchoolClass $class)
    {
        $class->update($request->validated());

        return $class->fresh('cycle');
    }

    public function destroy(SchoolClass $class)
    {
        abort_if($class->students()->exists(), 422, 'Impossible de supprimer une classe qui a des élèves.');
        $class->delete();

        return response()->noContent();
    }

    public function cycles()
    {
        return SchoolCycle::orderBy('sort_order')->get();
    }
}
