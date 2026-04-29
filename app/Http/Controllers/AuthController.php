<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

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
                "email.required" => "Поле обязательно для заполнения",
                "email.email" => "Введите корректный email адрес",
                "password.required" => "Поле обязательно для заполнения",
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
        if ($user->is_blocked) {
            Auth::logout();
            return response()->json(['message' => 'Ваш аккаунт заблокирован'], 403);
        }

        $token = $user->createToken("auth_token")->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Вход выполнен',
            'data' => [
                'token' => $token,
                'user' => $user->load('media', 'role')
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
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
                'name' => 'required|string|regex:/^[\pL\s\-]+$/u',
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
                'name.regex'    => 'В имени не должно быть цифр =(',
                'birth_date.string' => 'Дата рождения должна быть строкой',
                'bio.string' => 'Биография должна быть строкой',
                'avatar.image' => 'Файл аватарки должен быть изображением',
                'avatar.max' => 'Размер аватарки не должен превышать 5MB',
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
                'user' => $user->load('media', 'role')
            ]
        ], 201);
    }
}
