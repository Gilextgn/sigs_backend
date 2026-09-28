<?php

namespace Modules\Debtors\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\AcademicYears\Models\SchoolSetting;
use Modules\Debtors\Models\ReminderLog;
use Modules\Payments\Services\ReceiptNotifier;
use Modules\Students\Models\Student;
use Modules\Tranches\Models\TuitionInstallment;

/**
 * Relances de paiement. Les échéances sont celles saisies sur les tranches
 * (tuition_installments.due_date) ; le directeur règle le reste : texte du
 * message, combien de jours avant prévenir, fréquence après l'échéance,
 * canaux. Une famille reçoit un seul message regroupant ses tranches dues.
 */
class ReminderService
{
    public const DEFAULT_TEMPLATE = "Bonjour {parent},\n{ecole} vous rappelle que la scolarité de {eleve} ({classe}) reste à régler :\n{detail}\nTotal : {montant}.\nMerci de passer à la caisse avant l'échéance. Si vous avez déjà payé, ne tenez pas compte de ce message.\nLa Direction";

    public const PLACEHOLDERS = ['parent', 'eleve', 'classe', 'montant', 'detail', 'echeance', 'ecole'];

    public function __construct(private DebtCalculator $calculator)
    {
    }

    /** Réglage de l'école, complété par les valeurs par défaut. */
    public function config(): array
    {
        $stored = json_decode((string) SchoolSetting::where('setting_key', 'reminders')->value('setting_value'), true) ?: [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? false),
            // Jours avant l'échéance où la famille est prévenue (0 = le jour même).
            'days_before' => array_values(array_map('intval', $stored['days_before'] ?? [7, 1])),
            // Après l'échéance : un rappel tous les N jours (0 = aucun).
            'overdue_every' => (int) ($stored['overdue_every'] ?? 7),
            'channels' => array_values($stored['channels'] ?? ['whatsapp', 'email']),
            'template' => (string) ($stored['template'] ?? self::DEFAULT_TEMPLATE),
            'last_auto_run' => $stored['last_auto_run'] ?? null,
        ];
    }

    public function saveConfig(array $config): array
    {
        $merged = [...$this->config(), ...$config];
        SchoolSetting::updateOrCreate(
            ['school_id' => \App\Support\CurrentSchool::id(), 'setting_key' => 'reminders'],
            ['setting_value' => json_encode($merged, JSON_UNESCAPED_UNICODE)],
        );

        return $this->config();
    }

    /**
     * Familles ayant une tranche non soldée dont l'échéance est passée ou
     * arrive dans $horizonDays jours. Une ligne par élève.
     */
    public function candidates(int $horizonDays, ?CarbonImmutable $today = null): Collection
    {
        $today ??= CarbonImmutable::today();
        $limit = $today->addDays($horizonDays)->toDateString();
        $dueDates = TuitionInstallment::whereNotNull('due_date')->whereDate('due_date', '<=', $limit)->pluck('due_date', 'id')
            ->map(fn ($date) => CarbonImmutable::parse($date)->startOfDay());

        if ($dueDates->isEmpty()) {
            return collect();
        }

        $students = Student::with(['schoolClass.installments', 'guardian'])->where('status', 'active')->get();
        $debts = $this->calculator->calculateMany(
            $students->map(fn (Student $s) => ['student_id' => $s->id, 'class_id' => $s->class_id, 'class' => $s->schoolClass]),
            null, null, withAllLines: true,
        );
        $config = $this->config();
        $lastSent = ReminderLog::where('status', 'sent')->whereIn('student_id', $students->pluck('id'))
            ->selectRaw('student_id, MAX(created_at) as last_at')->groupBy('student_id')->pluck('last_at', 'student_id');

        return $students->map(function (Student $student) use ($debts, $dueDates, $today, $config, $lastSent) {
            $items = collect($debts[$student->id]['lines'] ?? [])
                ->filter(fn ($line) => $line['type'] === 'TRANCHE' && $line['remaining'] > 0 && $dueDates->has($line['id']))
                ->map(fn ($line) => [
                    'label' => $line['label'],
                    'remaining' => $line['remaining'],
                    'due_date' => $dueDates[$line['id']]->toDateString(),
                    'days' => (int) $today->diffInDays($dueDates[$line['id']], false), // < 0 : échéance passée
                ])
                ->sortBy('due_date')->values();

            if ($items->isEmpty()) {
                return null;
            }

            $row = [
                'student_id' => $student->id,
                'matricule' => $student->matricule,
                'full_name' => $student->fullName(),
                'class' => $student->schoolClass?->label,
                'guardian' => $student->guardian?->full_name,
                'phone' => $student->guardian?->phone,
                'whatsapp' => $student->guardian?->whatsapp ? ReceiptNotifier::normalizePhone($student->guardian->whatsapp) : null,
                'email' => $student->guardian?->email,
                'items' => $items->all(),
                'total' => round((float) $items->sum('remaining'), 2),
                'days' => $items->min('days'),
                'last_reminded_at' => isset($lastSent[$student->id]) ? CarbonImmutable::parse($lastSent[$student->id])->toIso8601String() : null,
            ];

            return [...$row, 'message' => $this->render($config['template'], $row), 'due_today' => $this->isDueToday($row, $config)];
        })->filter()->sortBy('days')->values();
    }

    /** Remplace {parent}, {eleve}… par les valeurs de la famille. */
    public function render(string $template, array $row): string
    {
        $money = fn ($v) => number_format((float) $v, 0, ',', ' ').' F CFA';
        $date = fn ($d) => CarbonImmutable::parse($d)->format('d/m/Y');
        $detail = collect($row['items'])->map(fn ($item) => '- '.$item['label'].' : '.$money($item['remaining'])
            .($item['days'] < 0 ? ' (échue depuis le '.$date($item['due_date']).')' : ' (échéance le '.$date($item['due_date']).')'))->implode("\n");

        return strtr($template, [
            '{parent}' => $row['guardian'] ?? 'Madame, Monsieur',
            '{eleve}' => $row['full_name'],
            '{classe}' => $row['class'] ?? '',
            '{montant}' => $money($row['total']),
            '{detail}' => $detail,
            '{echeance}' => $date($row['items'][0]['due_date']),
            '{ecole}' => ReceiptNotifier::schoolName(\App\Support\CurrentSchool::id()),
        ]);
    }

    /** Un rappel est prévu aujourd'hui : J-n réglé, ou rappel périodique après l'échéance. */
    private function isDueToday(array $row, array $config): bool
    {
        foreach ($row['items'] as $item) {
            if ($item['days'] >= 0 && in_array($item['days'], $config['days_before'], true)) {
                return true;
            }
            if ($item['days'] < 0 && $config['overdue_every'] > 0 && (-$item['days']) % $config['overdue_every'] === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Envoie la relance d'une famille par les canaux automatiques disponibles
     * (e-mail, WhatsApp si l'API et son modèle sont configurés).
     *
     * @return ReminderLog[]
     */
    public function send(array $row, array $channels, ?int $userId): array
    {
        $logs = [];

        if (in_array('email', $channels, true) && $row['email']) {
            $logs[] = $this->attempt($row, 'email', $row['email'], $userId, fn () => Mail::raw($row['message'], fn ($m) => $m->to($row['email'])
                ->subject('Rappel de paiement — '.ReceiptNotifier::schoolName(\App\Support\CurrentSchool::id()))));
        }

        if (in_array('whatsapp', $channels, true) && $row['whatsapp'] && $this->whatsappAuto()) {
            $config = config('school.whatsapp');
            $logs[] = $this->attempt($row, 'whatsapp', $row['whatsapp'], $userId, fn () => Http::withToken($config['token'])->timeout(15)
                ->post("https://graph.facebook.com/v21.0/{$config['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $row['whatsapp'],
                    'type' => 'template',
                    'template' => [
                        'name' => $config['reminder_template'],
                        'language' => ['code' => $config['language']],
                        // Modèle Meta à une variable : le message du directeur, déjà rempli.
                        'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => str_replace("\n", ' ', $row['message'])]]]],
                    ],
                ])->throw());
        }

        return $logs;
    }

    /** WhatsApp ouvert à la main (lien wa.me) : tracé comme les autres envois. */
    public function logManual(array $row, int $userId): ReminderLog
    {
        return $this->attempt($row, 'whatsapp_manual', $row['whatsapp'] ?? $row['phone'], $userId, fn () => null);
    }

    /** Relance faite hors application (avis papier remis à l'élève…) : tracée. */
    public function logChannel(array $row, string $channel, int $userId): ReminderLog
    {
        return $this->attempt($row, $channel, null, $userId, fn () => null);
    }

    public function whatsappAuto(): bool
    {
        return app(ReceiptNotifier::class)->whatsappConfigured() && filled(config('school.whatsapp.reminder_template'));
    }

    /**
     * Relances automatiques du jour, une seule fois par jour et par école
     * (déclenchées à la première visite de la journée : pas de tâche planifiée
     * sur l'hébergement). Aucune famille n'est relancée deux fois le même jour.
     */
    public function runDaily(): int
    {
        $config = $this->config();
        $today = CarbonImmutable::today()->toDateString();

        if (! $config['enabled'] || $config['last_auto_run'] === $today) {
            return 0;
        }
        $this->saveConfig(['last_auto_run' => $today]);

        $alreadyToday = ReminderLog::whereDate('created_at', $today)->pluck('student_id')->all();
        $sent = 0;
        foreach ($this->candidates(max([0, ...$config['days_before']])) as $row) {
            if ($row['due_today'] && ! in_array($row['student_id'], $alreadyToday, true)) {
                $sent += count(array_filter($this->send($row, $config['channels'], null), fn ($log) => $log->status === 'sent')) > 0 ? 1 : 0;
            }
        }

        return $sent;
    }

    private function attempt(array $row, string $channel, ?string $recipient, ?int $userId, callable $send): ReminderLog
    {
        $error = null;
        try {
            $send();
        } catch (\Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
            Log::warning("Relance de l'élève {$row['student_id']} par {$channel} échouée", ['error' => $error]);
        }

        return ReminderLog::create([
            'student_id' => $row['student_id'],
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => $error ? 'failed' : 'sent',
            'error' => $error,
            'amount' => $row['total'],
            'message' => $row['message'],
            'sent_by_user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
