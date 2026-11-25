<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomParticipant;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // Lấy tin nhắn cũ của phòng
    public function index(Request $request, string $roomCode)
    {
        $room = Room::where('room_code', $roomCode)->firstOrFail();

        $hasJoinedRoom = RoomParticipant::where('room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if (!$hasJoinedRoom) {
            return response()->json([
                'message' => 'Bạn không có quyền xem tin nhắn của phòng này'
            ], 403);
        }

        $messages = Message::with('user:id,name,avatar')
            ->where('room_id', $room->id)
            ->orderBy('sent_at')
            ->paginate(30);

        return response()->json($messages);
    }

    // Lưu tin nhắn vào database
    public function store(Request $request, string $roomCode)
    {
        $data = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $room = Room::where('room_code', $roomCode)->firstOrFail();

        if ($room->status === 'ended') {
            return response()->json([
                'message' => 'Phòng đã kết thúc, không thể gửi tin nhắn'
            ], 422);
        }

        // Chỉ user đang ở trong phòng mới được gửi chat
        $participant = RoomParticipant::where('room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('left_at')
            ->first();

        if (!$participant) {
            return response()->json([
                'message' => 'Bạn không còn ở trong phòng'
            ], 403);
        }

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $request->user()->id,
            'content' => trim($data['content']),
            'message_type' => 'text',
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Gửi tin nhắn thành công',
            'data' => $message->load('user:id,name,avatar'),
        ], 201);
    }
}
