<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('session_type_id')->constrained('session_types')->cascadeOnDelete();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_timezone');
            $table->text('notes')->nullable();
            $table->dateTime('start_at_utc');
            $table->dateTime('end_at_utc');
            $table->string('status', 20)->default('confirmed');
            $table->timestamps();

            $table->index(['creator_id', 'status', 'start_at_utc', 'end_at_utc'], 'bookings_creator_overlap_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

