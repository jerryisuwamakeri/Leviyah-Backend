<?php

namespace App\Http\Controllers\API\Shop;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function myConversation(Request $request)
    {
        $conversation = Conversation::with([
            'messages' => fn($q) => $q->latest()->limit(50),
            'staff:id,name,avatar',
        ])->where('user_id', $request->user()->id)->latest()->first();

        return response()->json($conversation);
    }

    public function startConversation(Request $request)
    {
        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $conversation = Conversation::firstOrCreate(
            ['user_id' => $request->user()->id, 'status' => 'open'],
            ['subject' => $data['subject'] ?? 'Support Request']
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => User::class,
            'sender_id'       => $request->user()->id,
            'body'            => $data['message'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json($conversation->load('messages'), 201);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate(['message' => 'required|string|max:2000']);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => \App\Models\User::class,
            'sender_id'       => $request->user()->id,
            'body'            => $data['message'],
        ]);

        $conversation->update(['last_message_at' => now(), 'status' => 'open']);

        return response()->json($message);
    }
}
