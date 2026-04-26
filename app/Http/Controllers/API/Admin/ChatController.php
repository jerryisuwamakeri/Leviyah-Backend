<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Staff;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $query = Conversation::with(['user:id,name,email,avatar', 'lastMessage', 'staff:id,name'])
            ->withCount(['unreadMessages']);

        if ($request->status) $query->where('status', $request->status);

        return response()->json($query->latest('last_message_at')->paginate(20));
    }

    public function show(Conversation $conversation)
    {
        $conversation->load([
            'user:id,name,email,avatar',
            'staff:id,name,avatar',
            'messages.sender',
        ]);

        $conversation->messages()->where('sender_type', '!=', Staff::class)->update([
            'is_read' => true, 'read_at' => now(),
        ]);

        return response()->json($conversation);
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['message' => 'required|string|max:2000']);

        $staff = $request->user();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Staff::class,
            'sender_id'       => $staff->id,
            'body'            => $data['message'],
        ]);

        $conversation->update([
            'staff_id'        => $staff->id,
            'last_message_at' => now(),
        ]);

        return response()->json($message);
    }

    public function close(Conversation $conversation)
    {
        $conversation->update(['status' => 'closed']);
        return response()->json(['message' => 'Conversation closed.']);
    }

    public function assign(Request $request, Conversation $conversation)
    {
        $request->validate(['staff_id' => 'required|exists:staff,id']);
        $conversation->update(['staff_id' => $request->staff_id]);
        return response()->json($conversation->fresh()->load('staff:id,name'));
    }
}
