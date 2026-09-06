<?php

namespace Modules\Teachers\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Teachers\Http\Requests\StoreTeacherRequest;
use Modules\Teachers\Models\Teacher;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        return Teacher::when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 20));
    }

    public function store(StoreTeacherRequest $request)
    {
        return Teacher::create($request->validated() + ['school_id' => 1]);
    }

    public function update(StoreTeacherRequest $request, Teacher $teacher)
    {
        $teacher->update($request->validated());

        return $teacher->fresh();
    }

    public function destroy(Teacher $teacher)
    {
        $teacher->delete();

        return response()->noContent();
    }
}
