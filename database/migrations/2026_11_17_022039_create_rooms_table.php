<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('host_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('room_code')->unique();
            $table->string('name')->nullable();

            // waiting: đang chờ | active: đang gọi | ended: đã kết thúc
            $table->enum('status', ['waiting', 'active', 'ended'])
                ->default('waiting');

            $table->unsignedTinyInteger('max_participants')->default(4);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
