<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('email_key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->string('cta_text');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_email_templates');
    }
};
