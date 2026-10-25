<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('subject')->nullable();
            $table->string('preheader')->nullable();
            $table->string('eyebrow')->nullable();
            $table->string('title')->nullable();
            $table->text('intro')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->text('footer_note')->nullable();
            $table->timestamps();
        });

        // Start every email at its current wording, so nothing changes until edited.
        EmailTemplate::syncCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
