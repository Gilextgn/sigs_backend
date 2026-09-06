<?php

namespace Modules\Teachers\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Teachers\Models\Subject;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        return Subject::query()
            ->where('is_active', true)
            ->when($request->search, fn ($query, $search) => $query->where('label', 'like', "%{$search}%"))
            ->orderBy('label')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:subjects,code'],
            'label' => ['required', 'string', 'max:120'],
        ]);

        return response()->json(Subject::create($data + ['school_id' => 1]), 201);
    }

    public function update(Request $request, Subject $subject)
    {
        $subject->update($request->validate([
            'code' => ['sometimes', 'string', 'max:50'],
            'label' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ]));

        return $subject->fresh();
    }

    public function destroy(Subject $subject)
    {
        $subject->update(['is_active' => false]);

        return response()->noContent();
    }
}
