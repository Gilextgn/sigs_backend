<?php

namespace Modules\Debtors\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        return response()->json($this->debtors($request->integer('class_id') ?: null, $request->integer('tranche_id') ?: null));
    }

    /**
     * Débiteurs actifs triés du plus gros reste-dû au plus petit. Partagé
     * avec l'accueil et la barre du haut pour que tous les écrans
     * affichent les mêmes chiffres.
     */
    public function debtors(?int $classId = null, ?int $trancheId = null): Collection
    {
        $students = Student::query()
            ->with(['schoolClass.installments'])
            ->where('status', 'active')
            ->when($classId, fn ($q, $id) => $q->where('class_id', $id))
            ->get();

        $debts = $this->debtCalculator->calculateMany(
            $students->map(fn (Student $student) => [
                'student_id' => $student->id,
                'class_id' => $student->class_id,
                'class' => $student->schoolClass,
            ]),
            null,
            $trancheId,
        );

        return $students->map(fn (Student $student) => [
            'student_id' => $student->id,
            'matricule' => $student->matricule,
            'full_name' => $student->fullName(),
            'class' => $student->schoolClass?->label,
            ...$debts[$student->id],
        ])->filter(fn ($row) => $row['outstanding_amount'] > 0)
            ->sortByDesc('outstanding_amount')
            ->values();
    }
}
