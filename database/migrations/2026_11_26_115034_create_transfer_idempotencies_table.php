<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_idempotencies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Reduced length to fit the MySQL index limit
            $table->string('idempotency_key', 64);

            $table->string('request_hash', 64);

            $table->json('response_json')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'idempotency_key'],
                'user_id_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_idempotencies');
    }
};
