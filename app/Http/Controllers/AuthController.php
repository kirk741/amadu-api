<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate(
            [
                "email" => "required|email",
                "password" => "required|string"
            ],
            [
                "email.required" => "Поле email обязательно для заполнения",
                "email.email" => "Введите корректный email адрес",
                "password.required" => "Поле пароль обязательно для заполнения",
                "password.string" => "Пароль должен быть строкой"
            ]
        );

        if (!Auth::attempt($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный email или пароль'
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken("auth_token")->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Вход выполнен',
            'data' => [
                'token' => $token,
                'user' => $user->load('media')
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'Выход выполнен',
        ], 200);
    }

    public function register(Request $request)
    {
        $validated = $request->validate(
            [
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!#$%&*)(+\-=]).+$/',
                'name' => 'required|string',
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

        $user = User::create([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'name' => $validated['name'],
            'birth_date' => $validated['birth_date'] ?? null,
            'bio' => $validated['bio'] ?? null,
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $path = $file->store('avatars', 'public');

            $user->media()->create([
                'collection' => 'avatar',
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'success' => true,
            'message' => 'Пользователь зарегистрирован',
            'data' => [
                'token' => $token,
                'user' => $user->load('media')
            ]
        ], 201);
    }
}
