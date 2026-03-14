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

        if (!$schedule->where('is_booked', false)) {
            return response()->json([
                'success' => true,
                'message' => 'Нельзя удалить забронированное время'
            ], 422);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Запись удалена'
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:schedules,id'
        ]);

        $deletedCount = $request->user()->schedules()
            ->whereIn('id', $validated['ids'])
            ->where('is_booked', false)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Удалено записей: $deletedCount"
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'dates' => 'required|array',
            'dates.*'       => 'date|after_or_equal:today',
            'start_time'     => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_duration' => 'required|integer|min:15|max:120'
        ]);

        $slots = [];
        $userId = $request->user()->id;

        foreach ($validated['dates'] as $date) {
            $current = Carbon::parse($date . ' ' . $validated['start_time']);
            $end = Carbon::parse($date . ' ' . $validated['end_time']);

            while ($current->copy()->addMinutes($validated['slot_duration']) <= $end) {
                $slotStart = $current->copy();
                $slotEnd = $current->copy()->addMinutes($validated['slot_duration']);

                $exists = $request->user()->schedules()
                    ->where('start_time', $slotStart->toDateTimeString())
                    ->exists();

                if (!$exists) {
                    $slots[] = [
                        'id'         => (string) Str::ulid(),
                        'user_id'    => $userId,
                        'start_time' => $slotStart->toDateTimeString(),
                        'end_time'   => $slotEnd->toDateTimeString(),
                        'is_booked'  => false,
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ];
                }
                $current->addMinutes($validated['slot_duration']);
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
