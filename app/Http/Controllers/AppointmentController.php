<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Schedule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use \Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();
        $relation = match ($user->role->name) {
            'psychologist' => 'psychologistAppointments',
            'client'       => 'clientAppointments',
            default        => null,
        };

        if (!$relation || !method_exists($user, $relation)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $query = $request->user()->$relation()->with(['schedule', 'psychologist', 'client']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('status', 'like', "%{$search}%")
                    ->orWhereRelation('schedule', 'start_time', 'like', "%{$search}%")
                    ->orWhereRelation('schedule', 'end_time', 'like', "%{$search}%")
                    ->orWhereRelation('psychologist', 'name', 'like', "%{$search}%")
                    ->orWhereRelation('client', 'name', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(10)
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Appointment::class);

        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id,is_booked,0',
        ]);

        $slot = Schedule::findOrFail($validated['schedule_id']);


        $appointment = DB::transaction(function () use ($request, $slot) {
            $slot->update(['is_booked' => true]);
            return Appointment::create([
                'schedule_id'     => $slot->id,
                'psychologist_id' => $slot->user_id,
                'client_id'       => $request->user()->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $appointment->load('schedule', 'psychologist')
        ], 201);
    }

    public function show(Appointment $appointment)
    {
        $this->authorize('view', $appointment);

        return response()->json([
            'success' => true,
            'data' => $appointment->load('schedule', 'psychologist')
        ]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        $validated = $request->validate([
            'status' => 'required|string|in:scheduled,confirmed,completed,cancelled'
        ]);

        $user = $request->user();

        if ($user->role->name === 'client' && $validated['status'] !== 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Клиент может только отменить запись'
            ], 403);
        }

        if ($validated['status'] === 'cancelled') {
            DB::transaction(function () use ($request, $appointment, $validated) {
                $appointment->update($validated);
                $appointment->schedule->update(['is_booked' => false]);
            });
        } else {
            $appointment->update($validated);
        }

        return response()->json([
            'success' => true,
            'data' => $appointment->load('schedule', 'psychologist', 'client')
        ]);
    }

    public function destroy(Appointment $appointment)
    {
        $this->authorize('delete', $appointment);

        if ($appointment->status === 'cancelled') {
            return response()->json(['message' => 'Запись уже была отменена'], 422);
        }

        DB::transaction(function () use ($appointment) {
            $appointment->update(['status' => 'cancelled']);
            $appointment->schedule->update(['is_booked' => false]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Запись отменена'
        ]);
    }
}
