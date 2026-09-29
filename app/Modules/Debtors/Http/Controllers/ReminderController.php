<?php

namespace Modules\Debtors\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Debtors\Models\ReminderLog;
use Modules\Debtors\Services\ReminderService;
use Modules\Security\Models\AuditLog;

class ReminderController extends Controller
{
    public function __construct(private ReminderService $reminders)
    {
    }

    /** Familles à relancer (échues ou échéance dans N jours), réglage et historique récent. */
    public function index(Request $request)
    {
        $horizon = min(max($request->integer('horizon', 7), 0), 90);

        return response()->json([
            'config' => $this->reminders->config(),
            'whatsapp_auto' => $this->reminders->whatsappAuto(),
            'placeholders' => ReminderService::PLACEHOLDERS,
            'default_template' => ReminderService::DEFAULT_TEMPLATE,
            'default_notice' => ReminderService::DEFAULT_NOTICE,
            'rows' => $this->reminders->candidates($horizon),
            'history' => ReminderLog::with('sender:id,full_name')->orderByDesc('id')->limit(30)->get()
                ->map(fn (ReminderLog $log) => [
                    'id' => $log->id,
                    'student_id' => $log->student_id,
                    'student' => \Modules\Students\Models\Student::find($log->student_id)?->fullName(),
                    'channel' => $log->channel,
                    'status' => $log->status,
                    'error' => $log->error,
                    'amount' => (float) $log->amount,
                    'sent_by' => $log->sender?->full_name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'days_before' => ['present', 'array', 'max:5'],
            'days_before.*' => ['integer', 'between:0,60', 'distinct'],
            'overdue_every' => ['required', 'integer', 'between:0,60'],
            'channels' => ['present', 'array'],
            'channels.*' => ['in:email,whatsapp'],
            'template' => ['required', 'string', 'max:1000'],
            'notice_template' => ['nullable', 'string', 'max:1500'],
        ]);

        $config = $this->reminders->saveConfig($data);
        AuditLog::record('reminders.settings_updated', 'SchoolSetting', 'reminders', ['enabled' => $config['enabled'], 'days_before' => $config['days_before'], 'overdue_every' => $config['overdue_every']]);

        return response()->json($config);
    }

    /** Relancer maintenant les familles choisies, par les canaux automatiques réglés. */
    public function send(Request $request)
    {
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['integer']]);
        $rows = $this->reminders->candidates(90)->whereIn('student_id', $data['student_ids']);
        $channels = $this->reminders->config()['channels'];

        $results = $rows->map(fn ($row) => [
            'student_id' => $row['student_id'],
            'logs' => collect($this->reminders->send($row, $channels, $request->user()->id))->map->only(['channel', 'status', 'error'])->all(),
        ])->values();

        abort_if($results->every(fn ($r) => $r['logs'] === []), 422, 'Aucun envoi automatique possible : renseignez l’e-mail des parents, ou utilisez le bouton WhatsApp.');

        return response()->json($results);
    }

    /** Le secrétariat a ouvert WhatsApp avec le message pré-rempli : on le trace. */
    public function logManual(Request $request, int $studentId)
    {
        $row = $this->reminders->candidates(90)->firstWhere('student_id', $studentId);
        abort_unless($row, 404, 'Rien à relancer pour cet élève.');

        return response()->json($this->reminders->logManual($row, $request->user()->id), 201);
    }

    /** Relances automatiques du jour, lancées après la réponse (première visite de la journée). */
    public function auto()
    {
        app()->terminating(fn () => $this->reminders->runDaily());

        return response()->noContent();
    }
}
