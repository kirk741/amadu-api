<?php

namespace App\Http\Controllers;

use App\Models\PsychologistBook;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class PsychologistBookController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $query = PsychologistBook::with(['media', 'psychologist']);

        if ($request->filled('psychologist_id')) {
            $query->where('psychologist_id', $request->psychologist_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(3)
        ], 200);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PsychologistBook::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'comment' => 'nullable|string',
            'cover' => 'nullable|image|max:2048'
        ]);

        $book = PsychologistBook::create([
            'psychologist_id' => $request->user()->id,
            'title' => $validated['title'],
            'author' => $validated['author'],
            'comment' => $validated['comment']
        ]);

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $path = $file->store('book_covers', 'public');

            $book->media()->create([
                'collection' => 'cover',
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $book->load('media')], 201);
    }

    public function show(PsychologistBook $book)
    {
        return response()->json([
            'success' => true,
            'data' => $book->load(['media', 'psychologist'])
        ], 200);
    }

    public function update(Request $request, PsychologistBook $book)
    {
        $this->authorize('update', $book);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'author' => 'sometimes|string|max:255',
            'comment' => 'nullable|string',
            'cover' => 'nullable|image|max:5120'
        ]);

        $book->update($validated);

        if ($request->hasFile('cover')) {
            if ($book->media()) {
                Storage::disk('public')->delete($book->media()->file_path);
                $book->media()->delete();
            }
            $file = $request->file('cover');
            $path = $file->store('book_covers', 'public');

            $book->media()->create([
                'collection' => 'cover',
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Книга обновлена'], 200);
    }

    public function destroy(PsychologistBook $book)
    {
        $this->authorize('delete', $book);

        if($book->media()) {
            Storage::disk('public')->delete($book->media()->file_path);
            $book->media()->delete();
        }
        $book->delete();

        return response()->json(['success' => true, 'message' => 'Книга удалена'], 200);
    }
}
