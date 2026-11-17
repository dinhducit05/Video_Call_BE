<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Người dùng tạo phòng
    public function hostedRooms()
    {
        return $this->hasMany(Room::class, 'host_id');
    }

    // Các phòng người dùng đã/đang tham gia
    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_participants')
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

    // Các cuộc gọi người này tạo
    public function calledHistories()
    {
        return $this->hasMany(CallHistory::class, 'caller_id');
    }

    // Các cuộc gọi 1-1 người này nhận
    public function receivedHistories()
    {
        return $this->hasMany(CallHistory::class, 'receiver_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
