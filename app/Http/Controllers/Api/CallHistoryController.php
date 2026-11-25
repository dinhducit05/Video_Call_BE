<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallHistory;
use Illuminate\Http\Request;

class CallHistoryController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $histories = CallHistory::with([
            'room:id,room_code,name',
            'caller:id,name,avatar',
            'receiver:id,name,avatar',
        ])
            ->where(function ($query) use ($userId) {
                $query->where('caller_id', $userId)
                    ->orWhere('receiver_id', $userId)
                    ->orWhereHas('room.participants', function ($participantQuery) use ($userId) {
                        $participantQuery->where('users.id', $userId);
                    });
            })
            ->latest('started_at')
            ->paginate(15);

        return response()->json($histories);
    }
}
