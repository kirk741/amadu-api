<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'theme' => 'sometimes|in:light-theme,moon-theme,dark-theme',
            'notifications' => 'sometimes|boolean'
        ]);

        $currentSettings = $user->settings ?? [];
        $user->settings = array_merge($currentSettings, $validated);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Настройки обновлены',
            'data' => $user->settings
        ]);
    }
}
