<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licence', function (Blueprint $table) {
            $table->id();
            $table->text('cle')->nullable();
            $table->timestamp('essai_debut');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence');
    }
};
