<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::getGroup('general');

        return view('settings.index', compact('settings'));
    }

    public function write(Request $request)
    {
        $this->authorize('update', Setting::class);

        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_email' => 'nullable|email|max:255',
            'company_phone' => 'nullable|string|max:50',
            'company_website' => 'nullable|url|max:255',
            'company_address' => 'nullable|string|max:500',
        ]);

        foreach ($request->except(['_token', '_method']) as $key => $value) {
            Setting::setValue($key, $value ?? '');
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
