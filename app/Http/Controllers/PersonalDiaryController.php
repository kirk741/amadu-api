<?php

namespace App\Http\Controllers;

use App\Models\PersonalDiary;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PersonalDiaryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', PersonalDiary::class);
        
        $query = $request->user()->personalDiaries()->latest();

        if($request->filled('search')) {
            $search = $request->search;

            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PersonalDiary::class);

        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string'
        ]);

        $diary = $request->user()->personalDiaries()->create($validated);

        return response()->json([
            'success' => true, 
            'data' => $diary
        ], 201);
    }

    public function show(PersonalDiary $personalDiary)
    {
        $this->authorize('view', $personalDiary);
        
        return response()->json([
            'success' => true, 
            'data' => $personalDiary
        ]);
    }

    public function update(Request $request, PersonalDiary $personalDiary)
    {
        $this->authorize('update', $personalDiary);

        $validated = $request->validate([
            'title'   => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string'
        ]);

        $personalDiary->update($validated);

        return response()->json([
            'success' => true, 
            'data' => $personalDiary
        ]);
    }

    public function softDelete(PersonalDiary $personalDiary)
    {
        $this->authorize('softDelete', $personalDiary);
        
        $personalDiary->delete();
        
        return response()->json([
            'success' => true, 
            'message' => 'Запись перемещена в корзину'
        ]);
    }

    public function restore(string $id)
    {
        $diary = PersonalDiary::withTrashed()->findOrFail($id);
        $this->authorize('restore', $diary);

        $diary->restore();
        
        return response()->json([
            'success' => true, 
            'message' => 'Запись восстановлена'
        ]);
    }

    public function trash(Request $request)
    {
        $this->authorize("viewAny", PersonalDiary::class);
        
        $data = $request->user()->personalDiaries()
            ->onlyTrashed()
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function destroy(string $id)
    {
        $diary = PersonalDiary::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $diary);

        $diary->forceDelete();
        
        return response()->json([
            'success' => true, 
            'message' => 'Запись удалена'
        ]);
    }
}
