<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScheduleController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $query = Schedule::query();
        $isPsychologist = $request->user() && $request->user()->role->name === 'psychologist';

        if ($isPsychologist) {
            $query->where('user_id', $request->user()->id);
        } else {
            $query->where('is_booked', false);

            if ($request->has('psychologist_id')) {
                $query->where('user_id', $request->psychologist_id);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $query->with('user')->latest()->paginate(10)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time'   => 'required|date|after:start_time'
        ]);

        $schedule = $request->user()->schedules()->create($validated);

        return response()->json([
            'success' => true,
            'data' => $schedule
        ], 201);
    }

    public function show(Schedule $schedule)
    {
        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'start_time' => 'sometimes|required|date|after:now',
            'end_time'   => 'sometimes|required|date|after:start_time',
            'is_booked'  => 'sometimes|boolean'
        ]);

        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    public function destroy(Schedule $schedule)
    {
        $this->authorize('delete', $schedule);
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Запись удалена'
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time'     => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_duration' => 'required|integer|min:15|max:120'
        ]);

        $slots = [];
        $current = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $end = Carbon::parse($validated['date'] . ' ' . $validated['end_time']);

        while ($current->copy()->addMinutes($validated['slot_duration']) <= $end) {
            $slotStart = $current->copy();
            $slotEnd = $current->addMinutes($validated['slot_duration'])->copy();

            $exists = $request->user()->schedules()->where('start_time', $slotStart)->exists();

            if (!$exists) {
                $slots[] = [
                    'id' => Str::ulid(),
                    'user_id' => $request->user()->id,
                    'start_time' => $slotStart->toDateTimeString(),
                    'end_time' => $slotEnd->toDateTimeString(),
                    'is_booked' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (empty($slots)) {
            return response()->json([
                'message' => 'Слоты уже существуют или интервал слишком мал'
            ], 422);
        }

        Schedule::insert($slots);

        return response()->json([
            'success' => true,
            'count' => count($slots),
            'message' => 'Записи добавлены'
        ], 201);
    }
}
