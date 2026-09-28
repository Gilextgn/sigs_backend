<?php

namespace Modules\Payments\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\AcademicYears\Models\SchoolSetting;
use Modules\Payments\Models\Payment;
use Modules\Payments\Models\ReceiptDelivery;
use Modules\Platform\Models\School;

/**
 * Envoie le reçu au parent dès l'encaissement, par mail et/ou WhatsApp.
 *
 * C'est le principal garde-fou contre la fraude en caisse : si l'argent est
 * encaissé sans être saisi, le parent ne reçoit rien et le signale. Chaque
 * tentative (réussie ou non) est tracée dans receipt_deliveries.
 *
 * Un échec d'envoi ne doit jamais faire échouer l'encaissement : tout est
 * capturé et journalisé.
 */
class ReceiptNotifier
{
    /** @return array<int, ReceiptDelivery> */
    public function send(Payment $payment, ?array $channels = null, ?int $triggeredBy = null): array
    {
        $payment->loadMissing('student.guardian', 'student.schoolClass', 'items.tuitionInstallment', 'items.feeType');
        PaymentLineBalances::attach([$payment]);

        $guardian = $payment->student?->guardian;
        $deliveries = [];

        if ($guardian?->email && ($channels === null || in_array('email', $channels, true))) {
            $deliveries[] = $this->attempt($payment, 'email', $guardian->email, $triggeredBy, fn () => $this->sendEmail($payment, $guardian->email));
        }

        if ($guardian?->whatsapp && $this->whatsappConfigured() && ($channels === null || in_array('whatsapp', $channels, true))) {
            $number = self::normalizePhone($guardian->whatsapp);
            $deliveries[] = $this->attempt($payment, 'whatsapp', $number, $triggeredBy, fn () => $this->sendWhatsapp($payment, $number));
        }

        return $deliveries;
    }

    public function whatsappConfigured(): bool
    {
        return filled(config('school.whatsapp.token')) && filled(config('school.whatsapp.phone_number_id'));
    }

    /** Chiffres seuls, indicatif ajouté si absent : format attendu par WhatsApp (wa.me et API). */
    public static function normalizePhone(string $phone): string
    {
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed);

        if (str_starts_with($trimmed, '+')) {
            return $digits;
        }
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        $countryCode = (string) config('school.phone_country_code');

        return str_starts_with($digits, $countryCode) && strlen($digits) > 10 ? $digits : $countryCode.$digits;
    }

    public static function verificationUrl(Payment $payment): string
    {
        return config('school.public_app_url').'/verifier/'.$payment->verification_token;
    }

    public static function schoolName(int $schoolId): string
    {
        $name = SchoolSetting::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('setting_key', 'school_name')
            ->value('setting_value');

        return $name ?: (School::find($schoolId)?->name ?? 'SIGS');
    }

    private function attempt(Payment $payment, string $channel, string $recipient, ?int $triggeredBy, callable $send): ReceiptDelivery
    {
        $error = null;
        try {
            $send();
        } catch (\Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
            Log::warning("Envoi du reçu {$payment->reference_code} par {$channel} échoué", ['error' => $error]);
        }

        return ReceiptDelivery::create([
            'school_id' => $payment->school_id,
            'payment_id' => $payment->id,
            'channel' => $channel,
            'recipient' => self::mask($channel, $recipient),
            'status' => $error ? 'failed' : 'sent',
            'error' => $error,
            'triggered_by_user_id' => $triggeredBy,
            'created_at' => now(),
        ]);
    }

    private static function mask(string $channel, string $recipient): string
    {
        if ($channel === 'email') {
            [$user, $domain] = array_pad(explode('@', $recipient, 2), 2, '');

            return mb_substr($user, 0, 2).'***@'.$domain;
        }

        return '+'.substr($recipient, 0, -4).'****';
    }

    private function sendEmail(Payment $payment, string $email): void
    {
        $school = self::schoolName($payment->school_id);

        Mail::html($this->emailHtml($payment, $school), function ($message) use ($email, $school, $payment) {
            $message->to($email)->subject("Reçu de paiement {$payment->reference_code} — {$school}");
        });
    }

    private function sendWhatsapp(Payment $payment, string $number): void
    {
        $config = config('school.whatsapp');
        $student = $payment->student;

        $parameters = [
            $student->guardian->full_name,
            "{$student->first_name} {$student->last_name}",
            self::amount($payment->total_paid_amount).' '.config('school.currency'),
            $payment->reference_code,
            self::verificationUrl($payment),
        ];

        Http::withToken($config['token'])
            ->timeout(15)
            ->post("https://graph.facebook.com/v21.0/{$config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $number,
                'type' => 'template',
                'template' => [
                    'name' => $config['template'],
                    'language' => ['code' => $config['language']],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(fn ($text) => ['type' => 'text', 'text' => (string) $text], $parameters),
                    ]],
                ],
            ])
            ->throw();
    }

    private static function amount($value): string
    {
        return number_format((float) $value, 0, ',', ' ');
    }

    private function emailHtml(Payment $payment, string $school): string
    {
        $student = $payment->student;
        $currency = e(config('school.currency'));
        $rows = '';
        $remaining = 0.0;

        foreach ($payment->items as $item) {
            $remaining += (float) $item->remaining_after;
            $status = $item->remaining_after > 0 ? 'Reste '.self::amount($item->remaining_after) : 'Soldé';
            $rows .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">'.e($item->label).'</td>'
                .'<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right">'.self::amount($item->paid_amount).'</td>'
                .'<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right;color:#6b7280">'.e($status).'</td></tr>';
        }

        $remainingLine = $remaining > 0
            ? '<p style="color:#b87914"><strong>Reste à payer sur ces lignes : '.self::amount($remaining)." {$currency}</strong></p>"
            : '<p style="color:#0e9f6e"><strong>Toutes les lignes réglées sont soldées.</strong></p>';

        $url = e(self::verificationUrl($payment));

        return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#10151c">'
            .'<h2 style="margin-bottom:4px">'.e($school).'</h2>'
            .'<p style="margin-top:0;color:#5c6675">Reçu de paiement</p>'
            .'<p>Bonjour '.e($student->guardian->full_name).',</p>'
            .'<p>Nous confirmons la réception de votre paiement pour <strong>'.e("{$student->first_name} {$student->last_name}").'</strong>'
            .' ('.e($student->schoolClass?->label ?? '').', matricule '.e($student->matricule).').</p>'
            .'<p>Référence : <strong>'.e($payment->reference_code).'</strong><br>Date : '.e($payment->created_at?->format('d/m/Y H:i') ?? $payment->payment_date?->format('d/m/Y')).'</p>'
            .'<table style="width:100%;border-collapse:collapse;font-size:14px">'
            .'<tr style="background:#e4f5ee"><th style="padding:6px 8px;text-align:left">Libellé</th><th style="padding:6px 8px;text-align:right">Versé ('.$currency.')</th><th style="padding:6px 8px;text-align:right">Statut</th></tr>'
            .$rows
            .'<tr><td style="padding:8px"><strong>Total versé</strong></td><td style="padding:8px;text-align:right"><strong>'.self::amount($payment->total_paid_amount).'</strong></td><td></td></tr>'
            .'</table>'
            .$remainingLine
            .'<p>Vérifiez l\'authenticité de ce reçu : <a href="'.$url.'">'.$url.'</a></p>'
            .'<p style="font-size:12px;color:#5c6675">Si vous avez versé un montant différent, ou n\'avez pas reçu ce message après un paiement, contactez directement la direction de l\'établissement.</p>'
            .'</div>';
    }
}
