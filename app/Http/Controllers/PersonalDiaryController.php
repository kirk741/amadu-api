<?php

namespace App\Http\Controllers;

use App\Models\PersonalDiary;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PersonalDiaryController extends Controller
{
    use AuthorizesRequests;

    private function getPaginatedCollection(Request $request, $query)
    {
        $items = $query->latest()->get();

        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);

            $items = $items->filter(function ($diary) use ($search) {
                return mb_strpos(mb_strtolower($diary->title ?? ''), $search) !== false ||
                    mb_strpos(mb_strtolower($diary->content ?? ''), $search) !== false;
            });
        }

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = $items->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator(
            $currentItems,
            $items->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $paginated;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', PersonalDiary::class);
        
        $query = $request->user()->personalDiaries();
        $data = $this->getPaginatedCollection($request, $query);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function trash(Request $request)
    {
        $this->authorize("viewAny", PersonalDiary::class);
        
        $query = $request->user()->personalDiaries()->onlyTrashed();
        $data = $this->getPaginatedCollection($request, $query);

        return response()->json([
            'success' => true,
            'data' => $data
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

        return response()->json(['success' => true, 'data' => $diary], 201);
    }

    public function show(PersonalDiary $personalDiary)
    {
        $this->authorize('view', $personalDiary);
        return response()->json(['success' => true, 'data' => $personalDiary]);
    }

    public function update(Request $request, PersonalDiary $personalDiary)
    {
        $this->authorize('update', $personalDiary);

        $validated = $request->validate([
            'title'   => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string'
        ]);

        $personalDiary->update($validated);
        return response()->json(['success' => true, 'data' => $personalDiary]);
    }

    public function softDelete(PersonalDiary $personalDiary)
    {
        $this->authorize('softDelete', $personalDiary);
        $personalDiary->delete();
        return response()->json(['success' => true, 'message' => 'Запись перемещена в корзину']);
    }

    public function restore(string $id)
    {
        $diary = PersonalDiary::withTrashed()->findOrFail($id);
        $this->authorize('restore', $diary);

        $diary->restore();
        return response()->json(['success' => true, 'message' => 'Запись восстановлена']);
    }

    public function destroy(string $id)
    {
        $diary = PersonalDiary::withTrashed()->findOrFail($id);
        $this->authorize('delete', $diary);

        $diary->forceDelete();
        return response()->json(['success' => true, 'message' => 'Запись удалена']);
    }
}
