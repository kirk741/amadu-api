<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $query = Event::with('media')->latest('event_date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('event_date', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ], 200);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'event_date' => 'required|date|after:now',
            'location' => 'required|string|max:255',
            'poster' => 'nullable|image|max:2048'
        ]);

        $event = Event::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'event_date' => $validated['event_date'],
            'location' => $validated['location'],
        ]);

        if ($request->hasFile('poster')) {
            $path = $request->file('poster')->store('events', 'public');
            $event->media()->create([
                'collection' => 'poster',
                'file_path' => $path,
                'mime_type' => $request->file('poster')->getMimeType(),
                'size' => $request->file('poster')->getSize(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $event->load('media')], 201);
    }

    public function show(Event $event)
    {
        return response()->json(['success' => true, 'data' => $event->load('media')], 200);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'event_date' => 'sometimes|date|after:now',
            'location' => 'sometimes|string|max:255',
            'poster' => 'nullable|image|max:5120'
        ]);

        $event->update($validated);

        if ($request->hasFile('poster')) {
            foreach ($event->media as $item) {
                Storage::disk('public')->delete($item->file_path);
                $item->delete();
            }

            $path = $request->file('poster')->store('events', 'public');
            $event->media()->create([
                'collection' => 'poster',
                'file_path' => $path,
                'mime_type' => $request->file('poster')->getMimeType(),
                'size' => $request->file('poster')->getSize(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $event->load('media')], 200);
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        foreach ($event->media as $item) {
            Storage::disk('public')->delete($item->file_path);
            $item->delete();
        }

        $event->delete();
        return response()->json(['success' => true, 'message' => 'Событие удалено'], 200);
    }
}
