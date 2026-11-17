<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'room_code',
        'name',
        'status',
        'max_participants',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'max_participants' => 'integer',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'room_participants')
            ->withPivot([
                'role',
                'joined_at',
                'left_at',
                'is_mic_on',
                'is_camera_on'
            ])
            ->withTimestamps();
    }

    public function participantRecords()
    {
        return $this->hasMany(RoomParticipant::class);
    }

    public function callHistories()
    {
        return $this->hasMany(CallHistory::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
