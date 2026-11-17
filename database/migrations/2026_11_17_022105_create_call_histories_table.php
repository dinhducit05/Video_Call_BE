<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('call_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                ->nullable()
                ->constrained('rooms')
                ->nullOnDelete();

            $table->foreignId('caller_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Null nếu là lịch sử phòng gọi nhóm
            $table->foreignId('receiver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // completed: hoàn thành | missed: nhỡ
            // rejected: từ chối | cancelled: hủy
            $table->enum('status', [
                'completed',
                'missed',
                'rejected',
                'cancelled'
            ])->default('completed');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_histories');
    }
};
