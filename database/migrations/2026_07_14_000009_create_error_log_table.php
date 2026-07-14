<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source');
            $table->json('payload');
            $table->text('error_message');
            $table->boolean('resolved')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_log');
    }
};
