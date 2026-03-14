<?php

namespace App\Http\Controllers;

use App\Models\FeelingsDiary;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class FeelingsDiaryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', FeelingsDiary::class);
        $query = $request->user()->feelingsDiaries()->latest();

        if($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('situation', 'like', "%{$search}%")
                ->orWhere('thoughts', 'like', "%{$search}%")
                ->orWhere('body_feelings', 'like', "%{$search}%")
                ->orWhere('feelings', 'like', "%{$search}%")
                ->orWhere('conclusion', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', FeelingsDiary::class);

        $validated = $request->validate([
            'situation' => 'nullable|string',
            'thoughts' => 'nullable|string',
            'body_feelings' => 'nullable|string',
            'feelings' => 'nullable|string',
            'conclusion' => 'nullable|string'
        ]);

        $diary = $request->user()->feelingsDiaries()->create([
            'situation' => $validated['situation'] ?? now()->format('Y-m-d H:i:s'),
            'thoughts' => $validated['thoughts'] ?? null,
            'body_feelings' => $validated['body_feelings'] ?? null,
            'feelings' => $validated['feelings'] ?? null,
            'conclusion' => $validated['conclusion'] ?? null
        ]);

        return response()->json(['success' => true, 'data' => $diary], 201);
    }

    public function show(FeelingsDiary $diary)
    {
        $this->authorize('view', $diary);
        return response()->json(['success' => true, 'data' => $diary], 200);
    }

    public function update(Request $request, FeelingsDiary $diary)
    {
        $this->authorize('update', $diary);

        $validated = $request->validate([
            'situation' => 'nullable|string',
            'thoughts' => 'nullable|string',
            'body_feelings' => 'nullable|string',
            'feelings' => 'nullable|string',
            'conclusion' => 'nullable|string'
        ]);

        $diary->update($validated);
        return response()->json(['success' => true, 'data' => $diary], 200);
    }

    public function softDelete(FeelingsDiary $diary)
    {
        $this->authorize('softDelete', $diary);
        $diary->delete();
        return response()->json(['success' => true, 'message' => 'Запись перемещена в корзину'], 200);
    }

    public function restore(string $id)
    {
        $diary = FeelingsDiary::withTrashed()->findOrFail($id);
        $this->authorize('restore', $diary);

        $diary->restore();
        return response()->json(['success' => true, 'message' => 'Запись восстановлена'], 200);
    }

    public function trash(Request $request)
    {
        $this->authorize("viewAny", FeelingsDiary::class);
        $data = $request->user()->feelingsDiaries()
            ->onlyTrashed()
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function destroy(string $id)
    {
        $diary = FeelingsDiary::withTrashed()->findOrFail($id);
        $this->authorize('delete', $diary);

        $diary->forceDelete();
        return response()->json(['success' => true, 'message' => 'Запись удалена окончательно'], 200);
    }
}
