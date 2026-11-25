<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomParticipant;
use App\Models\CallHistory;
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

    // Kiem tra quyen vao phong
    public function checkAccess(Request $request, string $roomCode)
    {
        $room = Room::where('room_code', $roomCode)->firstOrFail();

        $isParticipant = RoomParticipant::where('room_id', $roomCode)
            ->where('user_id', $request->user()->id)
            ->whereNull('left_at')
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'message' => 'Bạn chưa tham gia phòng này'
            ], 403);
        }

        if ($room->status == 'ended') {
            return response()->json([
                'message' => 'Phòng đã kết thúc'
            ], 422);
        }

        return response()->json([
            'message' => 'Có quyền tham gia cuộc gọi',
            'room' => $room,
            'user' => $request->user(),
        ]);
    }

    public function startCall(Request $request, string $roomCode)
    {
        $room = Room::where('room_code', $roomCode)->firstOrFail();

        if ($room->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Chỉ chủ phòng mới có quyền bắt đầu cuộc gọi'
            ], 403);
        }

        if ($room->status === 'ended') {
            return response()->json([
                'message' => 'Phòng đã kết thúc'
            ], 422);
        }

        if ($room->status === 'active') {
            return response()->json([
                'message' => 'Cuộc gọi đang diễn ra',
                'room' => $room
            ]);
        }

        $room->update([
            'status' => 'active',
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Bắt đầu cuộc gọi thành công',
            'room' => $room
        ]);
    }

    public function endCall(Request $request, string $roomCode)
    {
        $room = Room::where('room_code', $roomCode)->firstOrFail();

        if ($room->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Chỉ chủ phòng mới có quyền kết thúc cuộc gọi'
            ], 403);
        }

        if ($room->status === 'ended') {
            return response()->json([
                'message' => 'Phòng đã được kết thúc'
            ], 422);
        }

        $result = DB::transaction(function () use ($room) {
            $endedAt = now();
            $startedAt = $room->started_at ?? $room->created_at;

            $room->update([
                'status' => 'ended',
                'ended_at' => $endedAt,
            ]);

            // Đánh dấu tất cả người còn trong phòng là đã rời
            RoomParticipant::where('room_id', $room->id)
                ->whereNull('left_at')
                ->update([
                    'left_at' => $endedAt,
                    'is_mic_on' => false,
                    'is_camera_on' => false,
                ]);

            // Một phòng chỉ có một lịch sử gọi
            $history = CallHistory::updateOrCreate(
                ['room_id' => $room->id],
                [
                    'caller_id' => $room->host_id,
                    'receiver_id' => null,
                    'status' => 'completed',
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'duration_seconds' => $startedAt->diffInSeconds($endedAt),
                ]
            );

            return $history;
        });

        return response()->json([
            'message' => 'Kết thúc cuộc gọi thành công',
            'history' => $result,
        ]);
    }

    public function participants(Request $request, string $roomCode)
    {
        $room = Room::where('room_code', $roomCode)->firstOrFail();

        $participants = RoomParticipant::with('user:id,name,email,avatar')
            ->where('room_id', $room->id)
            ->whereNull('left_at')
            ->orderBy('joined_at')
            ->get();

        return response()->json([
            'room_code' => $room->room_code,
            'total' => $participants->count(),
            'participants' => $participants,
        ]);
    }

    public function updateMediaState(Request $request, string $roomCode)
    {
        $data = $request->validate([
            'is_mic_on' => 'required|boolean',
            'is_camera_on' => 'required|boolean',
        ]);

        $room = Room::where('room_code', $roomCode)->firstOrFail();

        $participant = RoomParticipant::where('room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('left_at')
            ->firstOrFail();

        $participant->update($data);

        return response()->json([
            'message' => 'Cập nhật trạng thái thiết bị thành công',
            'participant' => $participant,
        ]);
    }
}
