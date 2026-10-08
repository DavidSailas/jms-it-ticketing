<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\Branding;
use App\Support\LogoProcessor;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A company's own look: name, logo and colour.
 * Company admins change their own; super admins (JMS) can change any company's from its page.
 */
class BrandingController extends Controller
{
    public function edit(Request $request)
    {
        return view('branding.edit', ['company' => $this->ownCompany($request)]);
    }

    public function update(Request $request)
    {
        return $this->save($request, $this->ownCompany($request));
    }

    public function updateFor(Request $request, Company $company)
    {
        return $this->save($request, $company);
    }

    private function ownCompany(Request $request): Company
    {
        abort_unless($request->user()->company_id, 403, 'Your account is not attached to a company yet. Ask JMS to assign one.');

        return Company::findOrFail($request->user()->company_id);
    }

    private function save(Request $request, Company $company)
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'min:2', 'max:255', Rule::unique('companies', 'name')->ignore($company->id)],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo'        => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ], [
            'name.required'      => 'Enter the company name.',
            'name.unique'        => 'A company with this name already exists.',
            'brand_color.regex'  => 'Choose a colour from the picker, like #164a99.',
            'logo.image'         => 'The logo must be an image.',
            'logo.mimes'         => 'Use a PNG, JPG or WebP image.',
            'logo.max'           => 'The logo is too large. Keep it under 2 MB.',
            'logo.dimensions'    => 'The logo is too big. Use an image up to 4000 x 4000 pixels.',
            'logo.uploaded'      => 'The logo could not be uploaded. Keep it under 2 MB and try again.',
        ]);

        // Colour: empty or "reset" means the standard JMS blue.
        $color = $request->boolean('reset_color') ? null : strtolower($data['brand_color'] ?? '');
        if ($color === '' || $color === Branding::DEFAULT) {
            $color = null;
        }

        if ($color && Branding::contrastWithWhite($color) < Branding::MIN_CONTRAST) {
            throw ValidationException::withMessages([
                'brand_color' => 'That colour is too light: white text on buttons would be hard to read. Choose a darker one.',
            ]);
        }

        if (isset($data['name'])) {
            $company->name = trim($data['name']);
        }
        $company->brand_color = $color;

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $company->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $file = $request->file('logo');

            // Tidy the logo (transparent background, trimmed margins). If that is not possible, keep the original upload.
            $png = LogoProcessor::process($file->getRealPath());
            if ($png) {
                $company->logo_path = 'company-logos/' . Str::uuid() . '.png';
                Storage::disk('public')->put($company->logo_path, $png);
            } else {
                $company->logo_path = $file->store('company-logos', 'public');
            }
        }

        $company->save();

        return back()->with('success', 'Branding saved.');
    }
}
