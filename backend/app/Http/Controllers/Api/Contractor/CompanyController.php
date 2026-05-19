<?php

namespace App\Http\Controllers\Api\Contractor;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanySettingResource;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function show(): CompanySettingResource
    {
        $setting = CompanySetting::firstOrCreate(
            ['user_id' => auth()->id()],
            ['name' => '', 'address' => '', 'phone' => '', 'email' => '',
             'default_rate' => 0, 'default_tax_rate' => 0]
        );

        return new CompanySettingResource($setting);
    }

    public function update(Request $request): CompanySettingResource
    {
        $data = $request->validate([
            'name'           => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:255'],
            'defaultRate'    => ['nullable', 'numeric', 'min:0'],
            'defaultTaxRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $setting = CompanySetting::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'name'             => $data['name']           ?? '',
                'address'          => $data['address']         ?? '',
                'phone'            => $data['phone']           ?? '',
                'email'            => $data['email']           ?? '',
                'default_rate'     => $data['defaultRate']     ?? 0,
                'default_tax_rate' => $data['defaultTaxRate']  ?? 0,
            ]
        );

        return new CompanySettingResource($setting);
    }

    public function uploadLogo(Request $request): CompanySettingResource
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'max:512'],
        ]);

        $setting = CompanySetting::firstOrCreate(
            ['user_id' => auth()->id()],
            ['name' => '', 'address' => '', 'phone' => '', 'email' => '',
             'default_rate' => 0, 'default_tax_rate' => 0]
        );

        if ($setting->logo_path) {
            Storage::disk()->delete($setting->logo_path);
        }

        $ext  = $request->file('logo')->getClientOriginalExtension();
        $path = 'logos/' . auth()->id() . '/' . Str::uuid() . '.' . $ext;
        Storage::disk()->put($path, file_get_contents($request->file('logo')->getRealPath()));

        $setting->update(['logo_path' => $path]);

        return new CompanySettingResource($setting);
    }

    public function deleteLogo(): CompanySettingResource
    {
        $setting = CompanySetting::firstOrCreate(
            ['user_id' => auth()->id()],
            ['name' => '', 'address' => '', 'phone' => '', 'email' => '',
             'default_rate' => 0, 'default_tax_rate' => 0]
        );

        if ($setting->logo_path) {
            Storage::disk()->delete($setting->logo_path);
            $setting->update(['logo_path' => null]);
        }

        return new CompanySettingResource($setting);
    }
}
