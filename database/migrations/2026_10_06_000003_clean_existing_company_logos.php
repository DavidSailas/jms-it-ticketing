<?php

use App\Support\LogoProcessor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Logos uploaded before the automatic clean-up existed get the same treatment now. */
    public function up(): void
    {
        $disk = Storage::disk('public');

        foreach (DB::table('companies')->whereNotNull('logo_path')->get(['id', 'logo_path']) as $company) {
            if (! $disk->exists($company->logo_path)) {
                continue;
            }

            $png = LogoProcessor::process($disk->path($company->logo_path));
            if (! $png) {
                continue;
            }

            $new = 'company-logos/' . Str::uuid() . '.png';
            $disk->put($new, $png);
            $disk->delete($company->logo_path);

            DB::table('companies')->where('id', $company->id)->update(['logo_path' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the original files are replaced by cleaned versions.
    }
};
