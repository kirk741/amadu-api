<?php

namespace App\Http\Controllers;

use App\Models\SupportPhone;
use Illuminate\Http\Request;

class SupportPhoneController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => SupportPhone::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
        ]);

        $phone = SupportPhone::create($validated);
        return response()->json(['success' => true, 'data' => $phone], 201);
    }

    public function update(Request $request, SupportPhone $phone)
    {
        $validated = $request->validate([
            'phone' => 'sometimes|string|max:20',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
        ]);

        $phone->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Данные телефона обновлены',
            'data' => $phone
        ], 200);
    }

    public function destroy(SupportPhone $phone)
    {
        $phone->delete();
        return response()->json(['success' => true, 'message' => 'Телефон помощи удален']);
    }
}
