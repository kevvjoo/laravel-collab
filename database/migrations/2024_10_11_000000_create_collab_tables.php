<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This creates three tables:
     * 1. model_locks - stores active locks
     * 2. model_lock_sessions - tracks active editing sessions
     * 3. model_lock_history - audit trail of all lock activities
     */
    public function up(): void
    {
        // Main locks table
        Schema::create(config('collab.tables.locks', 'model_locks'), function (Blueprint $table): void {
            $table->id();
            
            // Polymorphic relationship - can lock any model
            $table->string('lockable_type');     // e.g., 'App\Models\Post'
            $table->unsignedBigInteger('lockable_id');  // e.g., 123
            
            // Who owns this lock
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable();
            
            // Lock configuration
            $table->string('strategy')->default('pessimistic');
            $table->json('locked_fields')->nullable();  // For field-level locking
            
            // Time tracking
            $table->timestamp('locked_at');
            $table->timestamp('expires_at');
            
            // Unique token for this lock
            $table->string('lock_token', 64)->unique();
            
            // Additional metadata
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();

            // Indexes for performance
            $table->index(['lockable_type', 'lockable_id'], 'lockable_index');
            $table->index('expires_at');
            $table->index('user_id');
        });

        // Sessions table - tracks who's actively viewing/editing
        Schema::create(config('collab.tables.sessions', 'model_lock_sessions'), function (Blueprint $table): void {
            $table->id();
            
            // Link to the lock
            $table->foreignId('lock_id')
                ->constrained(config('collab.tables.locks', 'model_locks'))
                ->cascadeOnDelete();
            
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel_name')->nullable();  // For broadcasting
            
            // Heartbeat tracking
            $table->timestamp('last_heartbeat');
            $table->boolean('is_active')->default(true);
            
            // For collaborative features (future)
            $table->json('cursor_position')->nullable();
            
            $table->timestamps();

            $table->index(['lock_id', 'is_active']);
            $table->index('last_heartbeat');
        });

        // History table - audit trail
        Schema::create(config('collab.tables.history', 'model_lock_history'), function (Blueprint $table): void {
            $table->id();
            
            // What was locked
            $table->string('lockable_type');
            $table->unsignedBigInteger('lockable_id');
            
            // Who did it
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // What happened
            $table->string('action');  // 'acquired', 'released', 'expired', 'forced'
            
            // How long was it held
            $table->integer('duration')->nullable();  // in seconds
            
            // What changed (if applicable)
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();

            $table->index(['lockable_type', 'lockable_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('collab.tables.history', 'model_lock_history'));
        Schema::dropIfExists(config('collab.tables.sessions', 'model_lock_sessions'));
        Schema::dropIfExists(config('collab.tables.locks', 'model_locks'));
    }
};
