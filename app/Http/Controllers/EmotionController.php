<?php

namespace App\Http\Controllers;

use App\Models\Emotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmotionController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Emotion::with('media')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'icon' => 'required|file|mimes:svg,svg+xml|max:512'
        ]);

        $emotion = Emotion::create([
            'name' => $validated['name']
        ]);

        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $path = $file->store('emotion_icons', 'public');

            $emotion->media()->create([
                'collection' => 'emotion_icons',
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize()
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $emotion->load('media')
        ], 201);
    }

    public function show(Emotion $emotion)
    {
        return response()->json([
            'success' => true,
            'data' => $emotion->load('media')
        ]);
    }

    public function update(Request $request, Emotion $emotion)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'icon' => 'sometimes|file|mimes:svg,svg+xml|max:512'
        ]);

        if ($request->hasFile('icon') && $emotion->media->isNotEmpty()) {
            foreach ($emotion->media as $media) {
                Storage::disk('public')->delete($media->file_path);
                $media->delete();
            }
        }

        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $path = $file->store('emotion_icons', 'public');

            $emotion->media()->create([
                'collection' => 'emotion_icons',
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize()
            ]);

            return response()->json(['success' => true, 'data' => $emotion->load('media')]);
        }

        $emotion->update($validated);

        return response()->json([
            'success' => true,
            'data' => $emotion->load('media')
        ]);
    }

    public function destroy(Emotion $emotion)
    {
        if ($emotion->media->isNotEmpty()) {
            foreach ($emotion->media as $media) {
                Storage::disk('public')->delete($media->file_path);
                $media->delete();
            }
        }

        $emotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Запись удалена'
        ]);
    }
}
