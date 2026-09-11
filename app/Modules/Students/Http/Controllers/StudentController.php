<?php

namespace Modules\Students\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Students\Http\Requests\StoreStudentRequest;
use Modules\Students\Http\Requests\UpdateStudentRequest;
use Modules\Students\Http\Resources\StudentResource;
use Modules\Students\Models\Guardian;
use Modules\Students\Models\Student;
use Modules\Students\Services\MatriculeGenerator;

class StudentController extends Controller
{
    public function __construct(private MatriculeGenerator $matriculeGenerator)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->search);
        $studentsQuery = Student::with(['schoolClass', 'guardian'])
            ->when($request->class_id, fn ($q, $id) => $q->where('class_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id');

        if ($search === '') {
            $students = $studentsQuery->paginate($request->integer('per_page', 20));
        } else {
            $students = $studentsQuery->get()
                ->filter(fn (Student $student) => str_contains(
                    mb_strtolower($student->matricule.' '.$student->fullName()),
                    mb_strtolower($search),
                ))
                ->values();
        }

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request)
    {
        $data = $request->validated();

        $student = DB::transaction(function () use ($data) {
            $guardianId = $data['guardian_id'] ?? Guardian::create($data['guardian'])->id;

            $identifiers = $this->matriculeGenerator->generate();

            return Student::create([
                ...$identifiers,
                'class_id' => $data['class_id'],
                'guardian_id' => $guardianId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
                // Explicite plutôt que de s'en remettre au défaut SQL : sinon
                // l'attribut reste nul sur le modèle fraîchement créé et la
                // réponse de l'API renvoie status: null au lieu de "active".
                'status' => 'active',
                'school_id' => 1,
            ]);
        });

        return new StudentResource($student->load('schoolClass', 'guardian'));
    }

    public function show(Student $student)
    {
        return new StudentResource($student->load('schoolClass', 'guardian', 'payments'));
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $student->update($request->validated());

        return new StudentResource($student->fresh('schoolClass', 'guardian'));
    }

    public function destroy(Student $student)
    {
        abort_if($student->payments()->exists(), 422, "Impossible de supprimer un élève ayant des paiements ; utilisez plutôt l'archivage.");
        $student->delete();

        return response()->noContent();
    }
}
