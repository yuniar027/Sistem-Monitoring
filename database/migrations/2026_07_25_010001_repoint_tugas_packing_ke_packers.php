<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cari nama constraint foreign key yang lama secara dinamis (Postgres),
        // supaya tidak perlu nebak nama persis yang di-generate otomatis.
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

            $table->foreign('ditugaskan_ke')->references('id')->on('packers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tugas_packing', function (Blueprint $table) {
            $table->dropForeign(['ditugaskan_ke']);
            $table->foreign('ditugaskan_ke')->references('id')->on('users')->nullOnDelete();
        });
    }
};
