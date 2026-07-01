<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    private const BOT_ID = '01JB0T0AI0ASS1STANT0000000';

    public function indexConversations()
    {
        $authId = Auth::id();

        $conversations = Conversation::where('client_id', $authId)
            ->orWhere('psychologist_id', $authId)
            ->with(['client.media', 'psychologist.media'])
            ->get();

        $hasBotChat = $conversations->contains(function ($conversation) {
            return $conversation->client_id === self::BOT_ID || $conversation->psychologist_id === self::BOT_ID;
        });

        if (!$hasBotChat) {
            $botUser = User::find(self::BOT_ID);

            if ($botUser) {
                $virtualBotConversation = new Conversation();
                $virtualBotConversation->id = null;
                $virtualBotConversation->client_id = $authId;
                $virtualBotConversation->psychologist_id = self::BOT_ID;
                $virtualBotConversation->created_at = now();

                $virtualBotConversation->partner = $botUser;
                $virtualBotConversation->partner_id = self::BOT_ID;

                $virtualBotConversation->last_message = (object)[
                    'body' => 'Привет! Я твой ИИ-помощник. Нажми, чтобы начать диалог.',
                    'created_at' => now()
                ];

                $conversations->push($virtualBotConversation);
            }
        }

        $formattedConversations = $conversations->map(function ($conversation) use ($authId) {
            if ($conversation->id !== null) {
                $conversation->last_message = Message::where('conversation_id', $conversation->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($conversation->client_id === $authId) {
                    $conversation->partner = $conversation->psychologist;
                    $conversation->partner_id = $conversation->psychologist_id;
                } else {
                    $conversation->partner = $conversation->client;
                    $conversation->partner_id = $conversation->client_id;
                }
            }
            return $conversation;
        })
            ->sortByDesc(function ($conversation) {
                return $conversation->last_message ? $conversation->last_message->created_at : $conversation->created_at;
            })
            ->values();

        return response()->json(['success' => true, 'data' => $formattedConversations], 200);
    }

    public function getOrCreateConversation(Request $request, $receiver_id)
    {
        $authId = Auth::id();

        $conversation = Conversation::where(function ($q) use ($authId, $receiver_id) {
            $q->where('client_id', $authId)->where('psychologist_id', $receiver_id);
        })->orWhere(function ($q) use ($authId, $receiver_id) {
            $q->where('client_id', $receiver_id)->where('psychologist_id', $authId);
        })->first();

        if (!$conversation) {
            if ($receiver_id === self::BOT_ID) {
                $clientId = $authId;
                $psychologistId = self::BOT_ID;
            } else {
                $receiver = User::findOrFail($receiver_id);
                if ($receiver->role === 'psychologist') {
                    $clientId = $authId;
                    $psychologistId = $receiver_id;
                } else {
                    $clientId = $receiver_id;
                    $psychologistId = $authId;
                }
            }

            try {
                $conversation = Conversation::create([
                    'id' => (string) Str::ulid(),
                    'type' => 'psychologist',
                    'client_id' => $clientId,
                    'psychologist_id' => $psychologistId,
                ]);

                if ($receiver_id === self::BOT_ID) {
                    Message::create([
                        'id' => (string) Str::ulid(),
                        'conversation_id' => $conversation->id,
                        'sender_id' => self::BOT_ID,
                        'body' => 'Привет! Я твой бережный ИИ-помощник. Расскажи, что тебя сейчас беспокоит?',
                        'content_type' => 'text'
                    ]);
                }
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $conversation = Conversation::where('client_id', $clientId)
                    ->where('psychologist_id', $psychologistId)
                    ->first();
            }
        }

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'messages' => $messages
        ], 200);
    }

    public function storeMessage(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'body' => 'required|string'
        ]);

        $authId = Auth::id();

        $userMessage = Message::create([
            'id' => (string) Str::ulid(),
            'conversation_id' => $request->conversation_id,
            'sender_id' => $authId,
            'body' => $request->body,
            'content_type' => 'text'
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);
        $isBotChat = ($conversation->client_id === self::BOT_ID || $conversation->psychologist_id === self::BOT_ID);

        if (!$isBotChat) {
            return response()->json([
                'success' => true,
                'data' => $userMessage
            ], 201);
        }

        try {
            $history = Message::where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse();

            $apiMessages = [];
            $apiMessages[] = [
                'role' => 'system',
                'content' => 'Ты — эмпатичный ИИ-психолог. Помогай пользователю справиться со стрессом. Отвечай только на русском языке, коротко. Ты должен только оказывать психологические консультации, не нужно писать код или решать математические задачи, мягко переводи всё в психологию'
            ];

            foreach ($history as $msg) {
                $apiMessages[] = [
                    'role' => $msg->sender_id === self::BOT_ID ? 'assistant' : 'user',
                    'content' => $msg->body
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Title' => 'Amadu App',
                'HTTP-Referer' => 'https://xn--80aexoc8f.site',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
                'messages' => $apiMessages,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                if (isset($result['choices'][0]['message']['content'])) {
                    $botReplyText = $result['choices'][0]['message']['content'];
                } else {
                    $botReplyText = 'Запрос прошел, но текст не найден. Ответ: ' . json_encode($result);
                }
            } else {
                $botReplyText = 'Ошибка OpenRouter API: ' . $response->status();
            }
        } catch (\Exception $e) {
            $botReplyText = 'Внутренняя ошибка сервера бэкенда: ' . $e->getMessage();
        }

        $botMessage = Message::create([
            'id' => (string) Str::ulid(),
            'conversation_id' => $conversation->id,
            'sender_id' => self::BOT_ID,
            'body' => $botReplyText,
            'content_type' => 'text'
        ]);

        return response()->json([
            'success' => true,
            'data' => $userMessage,
            'bot_reply' => $botMessage
        ], 201);
    }

    public function updateMessage(Request $request, $id)
    {
        $request->validate([
            'body' => 'required|string'
        ]);

        $message = Message::where('id', $id)
            ->where('sender_id', Auth::id())
            ->firstOrFail();

        if ($message->content_type === 'deleted') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя изменить удаленное сообщение'
            ], 400);
        }

        $message->update([
            'body' => $request->body
        ]);

        return response()->json([
            'success' => true,
            'data' => $message
        ], 200);
    }

    public function destroyMessage($id)
    {
        $message = Message::where('id', $id)
            ->where('sender_id', Auth::id())
            ->firstOrFail();

        $message->update([
            'body' => 'Сообщение удалено',
            'content_type' => 'deleted'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Сообщение успешно удалено'
        ], 200);
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id'
        ]);

        Message::where('conversation_id', $request->conversation_id)
            ->where('sender_id', '!=', Auth::id())
            ->whereNull('read_at')
            ->update([
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true
        ], 200);
    }
}
