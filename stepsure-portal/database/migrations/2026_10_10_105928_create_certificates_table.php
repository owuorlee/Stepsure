<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();            // Certificate ID printed on the certificate and used in the QR link
            $table->string('participant_name');
            $table->string('participant_email')->nullable();
            $table->string('course_title');
            $table->string('certificate_type')->default('Participation');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('venue')->nullable();             // e.g. Nairobi Safari Club, Online
            $table->string('class_code')->nullable();        // e.g. SOM 3/2026 (meaning to be confirmed by StepSure)
            $table->string('serial_no')->nullable();         // e.g. IA 2/2026 (meaning to be confirmed by StepSure)
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('issued');     // issued (made here) | imported (past certificate)
            $table->string('legacy_url')->nullable();        // e.g. the old Zikoro verification link
            $table->string('pdf_path')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->string('status')->default('valid');      // valid | revoked
            $table->text('revoked_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('participant_name');
            $table->index('class_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
