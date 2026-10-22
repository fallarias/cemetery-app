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
        Schema::create('deseased_info_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('desease_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('remarks_id');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('desease_id')->references('id')->on('deseased_info')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('remarks_id')->references('id')->on('remarks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deseased_info_logs');
    }
};
