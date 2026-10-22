<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('remarks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('desease_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->text('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('desease_id')->references('id')->on('deseased_info')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remarks');
    }
};
