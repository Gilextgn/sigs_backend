<?php

namespace Modules\AcademicYears\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Modules\AcademicYears\Models\SchoolLetterhead;
use Modules\AcademicYears\Models\SchoolSetting;

class SchoolSettingController extends Controller
{
    public function index()
    {
        return response()->json($this->settings());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['sometimes', 'string', 'max:160'],
            'currency' => ['sometimes', 'string', 'max:10'],
        ]);

        foreach ($data as $key => $value) {
            SchoolSetting::updateOrCreate(
                ['school_id' => \App\Support\CurrentSchool::id(), 'setting_key' => $key],
                ['setting_value' => $value],
            );
        }

        return response()->json($this->settings());
    }

    public function letterhead()
    {
        $letterhead = SchoolLetterhead::first();

        if ($letterhead) {
            return response(base64_decode($letterhead->data))
                ->header('Content-Type', $letterhead->mime_type)
                ->header('Cache-Control', 'private, max-age=3600');
        }

        // Image déposée avant le stockage en base, encore présente sur le disque.
        $path = $this->value('letterhead_path');
        abort_unless($path && File::exists(public_path($path)), 404);

        return response()->file(public_path($path));
    }

    public function uploadLetterhead(Request $request)
    {
        $request->validate([
            'letterhead' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ], [
            'letterhead.required' => 'Aucune image reçue.',
            'letterhead.image' => "Le fichier n'est pas une image.",
            'letterhead.mimes' => 'Formats acceptés : JPG ou PNG.',
            'letterhead.max' => "L'image dépasse 2 Mo.",
        ]);

        $file = $request->file('letterhead');

        SchoolLetterhead::updateOrCreate(
            ['school_id' => \App\Support\CurrentSchool::id()],
            ['mime_type' => $file->getMimeType(), 'data' => base64_encode($file->get())],
        );

        $this->forgetDiskLetterhead();

        return response()->json($this->settings());
    }

    public function deleteLetterhead()
    {
        SchoolLetterhead::where('school_id', \App\Support\CurrentSchool::id())->delete();
        $this->forgetDiskLetterhead();

        return response()->json($this->settings());
    }

    private function forgetDiskLetterhead(): void
    {
        $this->deleteFile($this->value('letterhead_path'));
        SchoolSetting::where('school_id', \App\Support\CurrentSchool::id())->where('setting_key', 'letterhead_path')->delete();
    }

    private function settings(): array
    {
        return [
            'school_name' => $this->value('school_name') ?? 'SIGS',
            'currency' => $this->value('currency') ?? 'XOF',
            'letterhead_url' => $this->letterheadUrl(),
        ];
    }

    private function value(string $key): ?string
    {
        return SchoolSetting::where('school_id', \App\Support\CurrentSchool::id())
            ->where('setting_key', $key)
            ->value('setting_value');
    }

    /**
     * L'URL change à chaque remplacement (paramètre v) : sans cela, le
     * navigateur réafficherait l'ancienne image depuis son cache.
     */
    private function letterheadUrl(): ?string
    {
        $letterhead = SchoolLetterhead::first();

        if ($letterhead) {
            return url('/api/settings/letterhead').'?v='.$letterhead->updated_at?->timestamp;
        }

        $path = $this->value('letterhead_path');

        return $path && File::exists(public_path($path)) ? url('/api/settings/letterhead') : null;
    }

    private function deleteFile(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
