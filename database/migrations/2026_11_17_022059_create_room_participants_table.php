<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('room_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                ->constrained('rooms')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // host: chủ phòng | participant: người tham gia
            $table->enum('role', ['host', 'participant'])
                ->default('participant');

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->boolean('is_mic_on')->default(true);
            $table->boolean('is_camera_on')->default(true);

            $table->timestamps();

            // Một user chỉ xuất hiện một lần trong một phòng
            $table->unique(['room_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_participants');
    }
};
