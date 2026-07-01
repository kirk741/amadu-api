<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Schedule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    use AuthorizesRequests;

    private array $relations = [
        'schedule:id,start_time,end_time',
        'psychologist:id,name',
        'client:id,name',
        'client.media'
    ];

    private array $hiddenFields = ['created_at', 'updated_at', 'notification_id'];

    public function index(Request $request)
    {
        $user = $request->user();
        $roleName = $user?->role?->name ?? 'guest';

        $relation = match ($roleName) {
            'psychologist' => 'psychologistAppointments',
            'client' => 'clientAppointments',
            default => null,
        };

        if (!$relation || !method_exists($user, $relation)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $query = $user->$relation()->with($this->relations);

        $expiredAppointments = $user->$relation()
            ->where('appointments.status', 'scheduled')
            ->whereHas('schedule', function ($q) {
                $q->where('start_time', '<', now());
            })
            ->with('schedule')
            ->get();

        if ($expiredAppointments->isNotEmpty()) {
            DB::transaction(function () use ($expiredAppointments) {
                foreach ($expiredAppointments as $appointment) {
                    $appointment->update(['status' => 'cancelled']);
                    $appointment->schedule?->update(['is_booked' => false]);
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->whereRelation('psychologist', 'name', 'like', "%{$search}%")
                    ->orWhereRelation('client', 'name', 'like', "%{$search}%")
                    ->orWhere('appointments.status', 'like', "%{$search}%");

                try {
                    if (str_contains($search, ':')) {
                        $q->orWhereHas('schedule', function ($sub) use ($search) {
                            $sub->where('start_time', 'like', "%{$search}%");
                        });
                    } else {
                        $dateSearch = null;
                        if (is_numeric($search) && (int)$search <= 31) {
                            $dateSearch = \Carbon\Carbon::now()->day((int)$search);
                        } else {
                            $dateSearch = \Carbon\Carbon::parse($search);
                        }

                        if ($dateSearch) {
                            $q->orWhereHas('schedule', function ($sub) use ($dateSearch) {
                                $sub->whereDate('start_time', $dateSearch->toDateString());
                            });
                        }
                    }
                } catch (\Throwable $e) {
                }
            });
        }

        $paginator = $query->latest()->paginate(10)->through(function ($appointment) {
            return $appointment->makeHidden($this->hiddenFields);
        });

        return response()->json([
            'success' => true,
            'data' => $paginator
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Appointment::class);

        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id,is_booked,0',
        ]);

        $slot = Schedule::findOrFail($validated['schedule_id']);
        $clientId = $request->user()->id;

        $appointment = DB::transaction(function () use ($clientId, $slot) {
            $slot->update(['is_booked' => true]);
            return Appointment::create([
                'schedule_id'     => $slot->id,
                'psychologist_id' => $slot->user_id,
                'client_id'       => $clientId,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $appointment->makeHidden($this->hiddenFields)
        ], 201);
    }

    public function show(Appointment $appointment)
    {
        $this->authorize('view', $appointment);

        return response()->json([
            'success' => true,
            'data' => $appointment->makeHidden($this->hiddenFields)->load($this->relations)
        ]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        $validated = $request->validate([
            'status' => 'required|string|in:scheduled,confirmed,completed,cancelled'
        ]);

        $user = $request->user();
        $roleName = $user?->role?->name ?? 'guest';

        if ($roleName === 'client' && $validated['status'] !== 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Клиент может только отменить запись'
            ], 403);
        }

        if ($validated['status'] === 'cancelled') {
            DB::transaction(function () use ($appointment, $validated) {
                $appointment->update($validated);
                $appointment->schedule->update(['is_booked' => false]);
            });
        } else {
            $appointment->update($validated);
        }

        return response()->json([
            'success' => true,
            'data' => $appointment->makeHidden($this->hiddenFields)->load($this->relations)
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

            if ($appointment->schedule) {
                $appointment->schedule->update(['is_booked' => false]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Запись отменена'
        ]);
    }
}
