<?php

namespace App\Http\Controllers;

use App\Models\FeelingsDiary;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FeelingsDiaryController extends Controller
{
    use AuthorizesRequests;

    private function getPaginatedCollection(Request $request, $query)
    {
        $items = $query->latest()->get();

        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);

            $items = $items->filter(function ($diary) use ($search) {
                return mb_strpos(mb_strtolower($diary->situation ?? ''), $search) !== false ||
                    mb_strpos(mb_strtolower($diary->thoughts ?? ''), $search) !== false ||
                    mb_strpos(mb_strtolower($diary->conclusion ?? ''), $search) !== false;
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
        $this->authorize('viewAny', FeelingsDiary::class);

        $query = $request->user()->feelingsDiaries();
        $data = $this->getPaginatedCollection($request, $query);

        return response()->json([
            'success' => true,
            'data' => $data
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

        $query = $request->user()->feelingsDiaries()->onlyTrashed();
        $data = $this->getPaginatedCollection($request, $query);

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
