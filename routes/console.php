<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Carbon\Carbon;
use Modules\Teachers\Models\ClassSchedule;
use Modules\Teachers\Models\TeachingSession;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sigs:backup', function () {
    $connection = config('database.connections.mysql');
    $directory = storage_path('app/backups');
    File::ensureDirectoryExists($directory);
    $filename = $directory.DIRECTORY_SEPARATOR.'sigs-'.now()->format('Y-m-d_H-i-s').'.sql';
    $dumpBinary = env('MYSQLDUMP_PATH', 'mysqldump');
    $command = sprintf(
        '%s --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
        escapeshellarg($dumpBinary),
        escapeshellarg($connection['host']),
        escapeshellarg($connection['port']),
        escapeshellarg($connection['username']),
        escapeshellarg($connection['password']),
        escapeshellarg($connection['database']),
        escapeshellarg($filename),
    );

    exec($command, $output, $exitCode);
    if ($exitCode !== 0 || ! File::exists($filename) || File::size($filename) === 0) {
        File::delete($filename);
        $this->fail('La sauvegarde a échoué. Vérifiez MYSQLDUMP_PATH et la connexion MySQL.');
    }

    $this->info("Sauvegarde créée : {$filename}");
})->purpose('Créer une sauvegarde SQL de la base SIGS');

Schedule::command('sigs:backup')->dailyAt('23:55');

Artisan::command('sigs:generate-sessions {date?}', function (?string $date = null) {
    $target = $date ? Carbon::parse($date) : today();
    $dayOfWeek = $target->dayOfWeekIso;
    $created = 0;

    ClassSchedule::with('assignment')
        ->where('day_of_week', $dayOfWeek)
        ->where('is_active', true)
        ->get()
        ->each(function (ClassSchedule $schedule) use ($target, &$created) {
            $exists = TeachingSession::where('teacher_assignment_id', $schedule->teacher_assignment_id)
                ->whereDate('session_date', $target)
                ->where('starts_at', $schedule->starts_at)
                ->exists();
            if ($exists) return;

            TeachingSession::create([
                'school_id' => 1,
                'academic_year_id' => $schedule->academic_year_id,
                'class_id' => $schedule->class_id,
                'subject_id' => $schedule->subject_id,
                'teacher_assignment_id' => $schedule->teacher_assignment_id,
                'session_date' => $target->toDateString(),
                'starts_at' => $schedule->starts_at,
                'ends_at' => $schedule->ends_at,
                'planned_minutes' => Carbon::parse($schedule->starts_at)->diffInMinutes(Carbon::parse($schedule->ends_at)),
            ]);
            $created++;
        });

    $this->info("{$created} séance(s) générée(s) pour {$target->toDateString()}.");
})->purpose('Générer les séances du jour depuis les emplois du temps');

Schedule::command('sigs:generate-sessions')->dailyAt('00:05');
