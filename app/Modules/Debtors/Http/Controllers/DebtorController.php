<?php

namespace Modules\Debtors\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Debtors\Services\DebtCalculator;
use Modules\Students\Models\Student;

class DebtorController extends Controller
{
    public function __construct(private DebtCalculator $debtCalculator)
    {
    }

    /**
     * Liste des débiteurs, avec filtre classe / tranche / classe+tranche,
     * équivalent applicatif de la vue SQL v_student_balances.
     */
    public function index(Request $request)
    {
        $query = Student::query()
            ->with(['schoolClass.installments'])
            ->where('status', 'active')
            ->when($request->class_id, fn ($q, $id) => $q->where('class_id', $id));

        $students = $query->get()->map(function (Student $student) use ($request) {
            $debt = $this->debtCalculator->calculate($student->id, $student->class_id, null, $request->tranche_id, $student->schoolClass);

            return [
                'student_id' => $student->id,
                'matricule' => $student->matricule,
                'full_name' => $student->fullName(),
                'class' => $student->schoolClass?->label,
                ...$debt,
            ];
        })->filter(fn ($row) => $row['outstanding_amount'] > 0)
            ->sortByDesc('outstanding_amount')
            ->values();

        return response()->json($students);
    }
}
