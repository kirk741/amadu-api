<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function me(Request $request)
    {
        $user = $request->user();
        $this->authorize('viewMe', $user);

        return response()->json([
            'success' => true,
            'data' => $user->load('media')->makeVisible(['settings'])
        ], 200);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $this->authorize('update', $user);

        if ($request->has('avatar') && is_string($request->avatar)) {
            $request->request->remove('avatar');
        }

        $validated = $request->validate(
            [
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => [
                    'nullable',
                    'confirmed',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
                'name' => 'sometimes|string|regex:/^[\pL\s\-]+$/u',
                'birth_date' => 'nullable|string|before:2020',
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
                'password.confirmed' => 'Пароли не совпадают',
                'name.required' => 'Введите имя',
                'name.string' => 'Имя должно быть строкой',
                'name.regex' => 'В имени не должно быть цифр =(',
                'birth_date.string' => 'Дата рождения должна быть строкой',
                'birth_date.before' => 'Вы должны быть рождены после 2019 года чтобы использовать приложение =(',
                'bio.string' => 'Описание профиля должно быть строкой',
                'avatar.image' => 'Файл аватара должен быть изображением',
                'avatar.max' => 'Размер аватара не должен превышать 5MB',
            ]
        );

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        $file = $request->file('avatar');

        if (!$file && $request->has('avatar')) {
            $file = $request->avatar;
        }

        if ($file && $file instanceof \Illuminate\Http\UploadedFile) {
            $avatarMedia = $user->media ? $user->media->where('collection', 'avatar') : collect();

            if ($avatarMedia->isNotEmpty()) {
                foreach ($avatarMedia as $oldMedia) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldMedia->file_path)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($oldMedia->file_path);
                    }
                    $oldMedia->delete();
                }
            }

            $path = $file->store('avatars', 'public');

            $user->media()->create([
                'collection' => 'avatar',
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        unset($validated['avatar']);
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

        if ($user->media) {
            foreach ($user->media as $media) {
                if (Storage::disk('public')->exists($media->file_path)) {
                    Storage::disk('public')->delete($media->file_path);
                }
                $media->delete();
            }
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Аккаунт удален'
        ], 200);
    }

    public function index(Request $request)
    {
        $currentUser = auth('sanctum')->user();
        $query = User::query()->with(['media', 'role']);

        if (!$currentUser) {
            $query->whereHas('role', fn($q) => $q->where('name', 'psychologist'));
        } else {
            $role = $currentUser->role?->name;
            if ($role === 'admin') {
            } elseif ($role === 'psychologist') {
                $query->whereHas('role', fn($q) => $q->where('name', 'client'));
            } elseif ($role === 'client') {
                $query->whereHas('role', fn($q) => $q->where('name', 'psychologist'));
            }
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
