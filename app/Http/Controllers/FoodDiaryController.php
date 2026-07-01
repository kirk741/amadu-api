<?php

namespace App\Http\Controllers;

use App\Models\FoodDiary;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;

class FoodDiaryController extends Controller
{
    use AuthorizesRequests;

    private function getPaginatedCollection(Request $request, $query)
    {
        $items = $query->with('media')->latest()->get();

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
        $this->authorize("viewAny", FoodDiary::class);

        $query = $request->user()->foodDiaries();
        $data = $this->getPaginatedCollection($request, $query);

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function trash(Request $request)
    {
        $this->authorize("viewAny", FoodDiary::class);

        $query = $request->user()->foodDiaries()->onlyTrashed();
        $data = $this->getPaginatedCollection($request, $query);

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function store(Request $request)
    {
        $this->authorize('create', FoodDiary::class);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'required_without:cover|nullable|string',
            'cover' => 'required_without:content|nullable|image|max:2048'
        ]);

        $foodDiary = $request->user()->foodDiaries()->create([
            'title' => $validated['title'] ?? now()->format('Y-m-d H:i:s'),
            'content' => $validated['content'] ?? null
        ]);

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $path = $file->store('foodDiaryCovers', 'local');

            $foodDiary->media()->create([
                'collection' => 'foodDiaryCovers',
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize()
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $foodDiary->load('media')
        ], 201);
    }

    public function show(FoodDiary $diary)
    {
        $this->authorize('view', $diary);
        return response()->json([
            'success' => true,
            'data' => $diary->load('media')
        ], 200);
    }

    public function update(Request $request, FoodDiary $diary)
    {
        $this->authorize('update', $diary);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'required_without:cover|nullable|string',
            'cover' => 'required_without:content|nullable|image|max:2048'
        ]);

        $diary->update([
            'title' => $validated['title'] ?? $diary->title,
            'content' => $validated['content'] ?? $diary->content
        ]);

        if ($request->hasFile('cover')) {
            if ($diary->media->isNotEmpty()) {
                foreach ($diary->media as $item) {
                    Storage::disk('local')->delete($item->file_path);
                    $item->delete();
                }
            }

            $file = $request->file('cover');
            $path = $file->store('foodDiaryCovers', 'local');

            $diary->media()->create([
                'collection' => 'foodDiaryCovers',
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize()
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $diary->load('media')
        ], 200);
    }

    public function softDelete(FoodDiary $diary)
    {
        $this->authorize('softDelete', $diary);
        $diary->delete();
        return response()->json(['success' => true, 'message' => 'Запись перемещена в корзину'], 200);
    }

    public function restore(string $id)
    {
        $diary = FoodDiary::withTrashed()->with('media')->findOrFail($id);
        $this->authorize('restore', $diary);
        $diary->restore();
        return response()->json(['success' => true, 'message' => 'Запись восстановлена'], 200);
    }

    public function destroy(string $id)
    {
        $diary = FoodDiary::withTrashed()->with('media')->findOrFail($id);
        $this->authorize('delete', $diary);

        if ($diary->media->isNotEmpty()) {
            foreach ($diary->media as $item) {
                Storage::disk('local')->delete($item->file_path);
                $item->delete();
            }
        }

        $diary->forceDelete();
        return response()->json(['success' => true, 'message' => 'Запись удалена'], 200);
    }

    public function getFile(FoodDiary $diary)
    {
        $this->authorize('view', $diary);

        $media = $diary->media()->first();

        if (!$media) {
            return response()->json(['message' => 'Файл не найден в базе'], 404);
        }

        if (!Storage::disk('local')->exists($media->file_path)) {
            return response()->json(['message' => 'Файл отсутствует на диске'], 404);
        }

        return Storage::disk('local')->response($media->file_path);
    }
}
