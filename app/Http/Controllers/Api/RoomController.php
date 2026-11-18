<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    // Danh sách phòng đang chờ hoặc đang gọi
    public function index()
    {
        $room = Room::with([
            'host:id,name,email,avatar',
            'participants:id,name,avatar'
        ])
            ->whereIn('status', ['waiting', 'active'])
            ->latest()
            ->paginate(10);
        return response()->json($room);
    }

    // Tao phong moi
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'max_participants' => 'required|integer|min:2|max:4',
        ]);

        do {
            $roomCode = strtoupper(Str::random(6));
        } while (Room::where('room_code', $roomCode)->exists());

        $room = DB::transaction(function () use ($request, $data, $roomCode) {
            $room = Room::create([
                'host_id' => $request->user()->id,
                'room_code' => $roomCode,
                'name' => $data['name'] ?? 'Phòng của ' . $request->user()->name,
                'max_participants' => $data['max_participants'],
                'status' => 'waiting',
            ]);
            // Chủ phòng tự động tham gia phòng
            RoomParticipant::create([
                'room_id' => $room->id,
                'user_id' => $request->user()->id,
                'role' => 'host',
                'joined_at' => now(),
                'is_mic_on' => true,
                'is_camera_on' => true,
            ]);
            return $room;
        });
        return response()->json([
            'message' => 'Tạo phòng thành công',
            'room' => $room->load('host:id,name,email,avatar'),
            'join_link' => url('/room/' . $room->room_code),
        ], 201);
    }

    // Chi tiet 1 phong
    public function show(string $roomCode)
    {
        $room = Room::with([
            'host:id,name,email,avatar',
            'participants:id,name,email,avatar'
        ])
            ->where('room_code', $roomCode)
            ->firstOrFail();

        return response()->json([
            'room' => $room
        ]);
    }

    // Tham gia phong
    public function join(Request $request, string $roomCode)
    {
        $room = DB::transaction(function () use ($request, $roomCode) {
            $room = Room::where('room_code', $roomCode)
                ->lockForUpdate()
                ->firstOrFail();

            if ($room->status == 'ended') {
                abort(422, 'Phòng đã kết thúc');
            }

            $participant = RoomParticipant::where('room_id', $room->id)
                ->where('user_id', $request->user()->id)
                ->first();

            // Nếu chưa từng vào phòng, kiểm tra số người trước khi tạo mới
            if (!$participant) {
                $currentCount = RoomParticipant::where('room_id', $room->id)
                    ->whereNull('left_at')
                    ->count();

                if ($currentCount >= $room->max_participants) {
                    abort(422, 'Phòng đã đủ số lượng người tham gia');
                }
                $participant = RoomParticipant::create([
                    'room_id' => $room->id,
                    'user_id' => $request->user()->id,
                    'role' => 'participant',
                    'joined_at' => now(),
                    'is_mic_on' => true,
                    'is_camera_on' => true,
                ]);
            } else {
                // Người dùng đã vào trước đó thì cho phép vào lại
                $participant->update([
                    'joined_at' => now(),
                    'left_at' => null
                ]);
            }
            return $room;
        });

        return response()->json([
            'message' => 'Tham gia phòng thành công',
            'room' => $room->load([
                'host:id,name,email,avatar',
                'participants:id,name,email,avatar'
            ]),
        ]);
    }

    // Roi phong
    public function leave(Request $request, string $roomCode)
    {
        $room = Room::where('room_code', $roomCode)->firstOrFail();

        $participant = RoomParticipant::where('room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $participant->update([
            'left_at' => now(),
        ]);

        return response()->json([
            'message' => 'Đã rời phòng'
        ]);
    }
}
