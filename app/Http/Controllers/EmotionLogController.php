<?php

namespace App\Http\Controllers;

use App\Models\EmotionLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class EmotionLogController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize("viewAny", EmotionLog::class);
        return response()->json([
            'success' => true,
            'data' => $request->user()->emotionLogs()->with('emotion.media')->latest()->paginate(10)
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', EmotionLog::class);

        $validated = $request->validate([
            'emotion_id' => 'required|exists:emotions,id'
        ]);

        $emotionLog = $request->user()->emotionLogs()->create($validated);

        return response()->json([
            'success' => true,
            'data' => $emotionLog->load('emotion.media')
        ], 201);
    }

    public function show(EmotionLog $emotionLog)
    {
        $this->authorize('view', $emotionLog);
        return response()->json([
            'success' => true,
            'data' => $emotionLog->load('emotion.media')
        ]);
    }

    public function update(Request $request, EmotionLog $emotionLog)
    {
        $this->authorize('update', $emotionLog);
        $validated = $request->validate([
            'emotion_id' => 'required|exists:emotions,id'
        ]);

        $emotionLog->update($validated);

        return response()->json([
            'success'=> true,
            'data'=> $emotionLog->load('emotion.media')
        ]);
    }

    public function destroy(EmotionLog $emotionLog)
    {
        $this->authorize('delete', $emotionLog);

        $emotionLog->delete();

        return response()->json([
            'success'=> true,
            'message'=> "Запись удалена"
        ]);
    }
}
