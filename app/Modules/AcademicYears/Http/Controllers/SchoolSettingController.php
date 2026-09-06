<?php

namespace Modules\AcademicYears\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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
                ['school_id' => 1, 'setting_key' => $key],
                ['setting_value' => $value],
            );
        }

        return response()->json($this->settings());
    }

    public function letterhead()
    {
        $path = $this->value('letterhead_path');

        abort_unless($path && File::exists(public_path($path)), 404);

        return response()->file(public_path($path));
    }

    public function uploadLetterhead(Request $request)
    {
        $request->validate([
            'letterhead' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ]);

        $oldPath = $this->value('letterhead_path');
        $directory = public_path('uploads/letterheads');
        File::ensureDirectoryExists($directory);

        $file = $request->file('letterhead');
        $filename = 'letterhead-'.uniqid('', true).'.'.$file->extension();
        $file->move($directory, $filename);

        SchoolSetting::updateOrCreate(
            ['school_id' => 1, 'setting_key' => 'letterhead_path'],
            ['setting_value' => 'uploads/letterheads/'.$filename],
        );

        $this->deleteFile($oldPath);

        return response()->json($this->settings());
    }

    public function deleteLetterhead()
    {
        $this->deleteFile($this->value('letterhead_path'));
        SchoolSetting::where('school_id', 1)->where('setting_key', 'letterhead_path')->delete();

        return response()->json($this->settings());
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
        return SchoolSetting::where('school_id', 1)
            ->where('setting_key', $key)
            ->value('setting_value');
    }

    private function letterheadUrl(): ?string
    {
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
