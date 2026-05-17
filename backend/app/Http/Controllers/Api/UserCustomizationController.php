<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserCustomizationResource;
use App\Models\UserCustomization;
use Illuminate\Http\Request;

class UserCustomizationController extends Controller
{
    public function show(): UserCustomizationResource
    {
        $customization = UserCustomization::firstOrCreate(
            ['user_id' => auth()->id()]
        );

        return new UserCustomizationResource($customization);
    }

    public function update(Request $request): UserCustomizationResource
    {
        $data = $request->validate([
            'ui'                     => ['sometimes', 'array'],
            'ui.theme'               => ['sometimes', 'string', 'in:light,dark'],
            'ui.use12h'              => ['sometimes', 'boolean'],
            'ui.activeVariant'       => ['sometimes', 'string', 'in:replicon,contractor'],
            'replicon'               => ['sometimes', 'array'],
            'replicon.jiraPattern'   => ['sometimes', 'string', 'max:255'],
            'contractor'             => ['sometimes', 'array'],
            'contractor.jiraPattern' => ['sometimes', 'string', 'max:255'],
        ]);

        $customization = UserCustomization::firstOrCreate(
            ['user_id' => auth()->id()]
        );

        $existing = $customization->configuration ?? [];

        foreach ($data as $ns => $values) {
            $existing[$ns] = array_merge($existing[$ns] ?? [], $values);
        }

        $customization->configuration = $existing;
        $customization->save();

        return new UserCustomizationResource($customization);
    }
}
