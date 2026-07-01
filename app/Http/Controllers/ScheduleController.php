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
        $user = request()->bearerToken() ? auth('sanctum')->user() : null;
        $query = Schedule::query();

        $isPsychologist = $user && $user->role && $user->role->name === 'psychologist';

        if ($isPsychologist) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('is_booked', false)->where('start_time', '>', Carbon::now());

            if ($request->has('psychologist_id')) {
                $query->where('user_id', $request->psychologist_id);
            }
        }

        $slots = $query->with(['appointments'])->latest()->get();

        $transformed = $slots->map(function ($slot) use ($user) {
            if (!$user || ($user->role->name !== 'psychologist' && $user->role->name !== 'admin')) {
                unset($slot->appointments);
            }
            return $slot;
        });

        return response()->json([
            'success' => true,
            'data' => $transformed
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time'   => 'required|date|after:start_time'
        ]);

        $start = $validated['start_time'];
        $end = $validated['end_time'];

        $exists = $request->user()->schedules()
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                });
            })->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Это время уже занято другим слотом в вашем расписании'
            ], 422);
        }

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

        if (isset($validated['start_time']) || isset($validated['end_time'])) {
            $start = $validated['start_time'] ?? $schedule->start_time;
            $end = $validated['end_time'] ?? $schedule->end_time;

            $exists = $request->user()->schedules()
                ->where('id', '!=', $schedule->id)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                })->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Это время уже занято другим слотом в вашем расписании'
                ], 422);
            }
        }
        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }
    public function destroy(Schedule $schedule)
    {
        $this->authorize('delete', $schedule);

        if ($schedule->is_booked) {
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
            'dates.*' => 'required|date_format:Y-m-d',
            'start_time' => 'required|string|regex:/^\d{2}:\d{2}$/',
            'end_time' => 'required|string|regex:/^\d{2}:\d{2}$/|after:start_time',
            'slot_duration' => 'required|integer|min:15|max:120',
            'gap' => 'required|integer|min:0|max:60'
        ]);

        $user = $request->user();
        $generatedCount = 0;
        $hasOverlapError = false;

        foreach ($validated['dates'] as $date) {
            $dayStart = \Carbon\Carbon::parse($date . ' ' . $validated['start_time']);
            $dayEnd = \Carbon\Carbon::parse($date . ' ' . $validated['end_time']);

            $currentStart = $dayStart->copy();

            while ($currentStart->copy()->addMinutes($validated['slot_duration'])->lte($dayEnd)) {
                $currentEnd = $currentStart->copy()->addMinutes($validated['slot_duration']);

                $startStr = $currentStart->toDateTimeString();
                $endStr = $currentEnd->toDateTimeString();

                $exists = $user->schedules()
                    ->where(function ($query) use ($startStr, $endStr) {
                        $query->where('start_time', '<', $endStr)
                            ->where('end_time', '>', $startStr);
                    })->exists();

                if ($exists) {
                    $hasOverlapError = true;
                    $currentStart->addMinutes($validated['slot_duration'] + $validated['gap']);
                    continue;
                }

                $user->schedules()->create([
                    'start_time' => $startStr,
                    'end_time' => $endStr,
                    'is_booked' => false
                ]);

                $generatedCount++;

                $currentStart->addMinutes($validated['slot_duration'] + $validated['gap']);
            }
        }

        if ($generatedCount === 0 && $hasOverlapError) {
            return response()->json([
                'success' => false,
                'message' => 'Все генерируемые слоты накладываются на существующее расписание'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Успешно сгенерировано слотов: {$generatedCount}"
        ], 201);
    }
}
