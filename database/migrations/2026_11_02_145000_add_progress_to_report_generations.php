<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_generations', function (Blueprint $table) {
            $table->string('status', 16)->default('ready')->index()->after('report_type');
            $table->unsignedTinyInteger('progress')->default(100)->after('row_count');
            $table->unsignedInteger('processed_rows')->default(0)->after('progress');
            $table->unsignedInteger('total_rows')->nullable()->after('processed_rows');
            $table->text('error')->nullable()->after('total_rows');
            $table->string('excel_path')->nullable()->after('error');
            $table->string('pdf_path')->nullable()->after('excel_path');
            $table->string('html_path')->nullable()->after('pdf_path');
        });

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::table('report_generations', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'progress',
                'processed_rows',
                'total_rows',
                'error',
                'excel_path',
                'pdf_path',
                'html_path',
            ]);
        });
    }
};
