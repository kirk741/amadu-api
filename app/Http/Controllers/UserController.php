<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function me(Request $request)
    {
        $user = $request->user();
        $this->authorize('viewMe', $user);
        return response()->json([
            'success' => true,
            'data' => $user->load('media')
        ], 200);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $validated = $request->validate(
            [
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'sometimes|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!#$%&*)(+\-=]).+$/',
                'name' => 'sometimes|string',
                'birth_date' => 'nullable|string',
                'bio' => 'nullable|string',
                'avatar' => 'nullable|image|max:5120'
            ],
            [
                'email.required' => 'Введите email',
                'email.email' => 'Введите корректный email адрес',
                'email.unique' => 'Пользователь с таким email уже существует',
                'password.required' => 'Введите пароль',
                'password.min' => 'Пароль должен содержать минимум 8 символов',
                'password.regex' => 'Пароль должен содержать заглавную букву, строчную букву, цифру и специальный символ',
                'name.required' => 'Введите имя',
                'name.string' => 'Имя должно быть строкой',
                'birth_date.string' => 'Дата рождения должна быть строкой',
                'bio.string' => 'Биография должна быть строкой',
                'avatar.image' => 'Файл аватара должен быть изображением',
                'avatar.max' => 'Размер аватара не должен превышать 5MB',
            ]
        );

        if ($request->hasFile('avatar')) {
            if ($user->media()) {
                $user->media()->delete();
            }
            $file = $request->file('avatar');
            $path = $file->store('avatars', 'public');

            $user->media()->create([
                'collection' => 'avatar',
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $user->update($validated);
        return response()->json([
            'success' => true,
            'message' => 'Данные изменены',
            'data' => $user->load('media')
        ], 200);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $this->authorize('delete', $user);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Аккаунт удален'
        ], 200);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $currentUser = $request->user();
        $query = User::query()->with(['media', 'role']);

        if ($currentUser) {
            $currentUser->loadMissing('role');

            if ($currentUser->role?->name === 'psychologist') {
                $query->whereHas('role', fn($q) => $q->where('name', 'client'));
            } elseif ($currentUser->role?->name === 'client') {
                $query->whereHas('role', fn($q) => $q->where('name', 'psychologist'));
            }
        } else {
            $query->whereHas('role', fn($q) => $q->where('name', 'psychologist'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ], 200);
    }

    public function show(User $model)
    {
        $this->authorize('view', $model);

        return response()->json([
            'success' => true,
            'data' => $model->load('media')
        ], 200);
    }

    public function block(User $model)
    {
        $this->authorize('manage', $model);
        $model->update(['is_blocked' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Пользователь заблокирован'
        ], 200);
    }

    public function unblock(User $model)
    {
        $this->authorize('manage', $model);
        $model->update(['is_blocked' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Пользователь разблокирован'
        ], 200);
    }

    public function setRole(Request $request, User $model)
    {
        $this->authorize('manage', $model);

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $model->update([
            'role_id' => $validated['role_id']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Роль пользователя успешно изменена',
            'data' => $model->load('role')
        ], 200);
    }
}
