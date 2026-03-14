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
        $query = $request->user()->emotionLogs()->with('emotion.media');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('created_at', 'like', "%{$search}%")
                    ->orWhereRelation('emotion', 'name', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(10)
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', EmotionLog::class);

        $validated = $request->validate([
            'emotion_id' => 'required|exists:emotions,id',
            'created_at' => 'required|date'
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
            'emotion_id' => 'sometimes|exists:emotions,id',
            'created_at' => 'sometimes|date'
        ]);

        $emotionLog->update($validated);

        return response()->json([
            'success' => true,
            'data' => $emotionLog->load('emotion.media')
        ]);
    }

    public function destroy(EmotionLog $emotionLog)
    {
        $this->authorize('delete', $emotionLog);

        $emotionLog->delete();

        return response()->json([
            'success' => true,
            'message' => "Запись удалена"
        ]);
    }
}
