<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->string('public_id', 16)->nullable()->unique()->after('id');
        });

        foreach (DB::table('scans')->whereNull('public_id')->orderBy('id')->get(['id']) as $scan) {
            do {
                $publicId = Str::upper(Str::random(10));
            } while (DB::table('scans')->where('public_id', $publicId)->exists());

            DB::table('scans')->where('id', $scan->id)->update(['public_id' => $publicId]);
        }
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
