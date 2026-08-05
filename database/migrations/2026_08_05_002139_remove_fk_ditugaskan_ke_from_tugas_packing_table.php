<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $constraint = DB::selectOne("
            SELECT tc.constraint_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_name = 'tugas_packing'
                AND tc.constraint_type = 'FOREIGN KEY'
                AND kcu.column_name = 'ditugaskan_ke'
        ");

        Schema::table('tugas_packing', function (Blueprint $table) use ($constraint) {
            if ($constraint) {
                $table->dropForeign($constraint->constraint_name);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tugas_packing', function (Blueprint $table) {
            $table->foreign('ditugaskan_ke')->references('id')->on('packers')->nullOnDelete();
        });
    }
};