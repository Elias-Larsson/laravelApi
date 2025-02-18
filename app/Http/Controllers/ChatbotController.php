<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatHistory;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;

class ChatbotController extends Controller
{
    public function chat(Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $user = Auth::user();
        $session_id = $request->session_id;
        
        if ($session_id) {
            $previousMessages = ChatHistory::where('user_id', $user->id)
                ->where('session_id', $session_id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($chat) => [
                    ['role' => 'user', 'content' => $chat->user_message],
                    ['role' => 'assistant', 'content' => $chat->bot_response],
                ])
                ->flatten(1)
                ->toArray();
            
            $messages = array_merge($previousMessages, [['role' => 'user', 'content' => $request->message]]);
        } else {
            $session_id = (string) Uuid::uuid4();
            $messages = [['role' => 'user', 'content' => $request->message]];
        }
        
        $response = Http::post('http://localhost:11434/api/chat', [
            'model' => 'mistral',
            'messages' => $messages,
            'stream' => false,
        ]);
        
        $bot_response = $response->json()['response'] ?? '';
        
        ChatHistory::create([
            'user_id' => $user->id,
            'session_id' => $session_id,
            'user_message' => $request->message,
            'bot_response' => $bot_response,
        ]);
        
        return response()->json(['response' => $bot_response, 'session_id' => $session_id]);
    }
}
