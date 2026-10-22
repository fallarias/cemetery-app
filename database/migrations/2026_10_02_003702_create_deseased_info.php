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
        Schema::create('deseased_info', function (Blueprint $table) {
            $table->id();
            $table->string('surname');
            $table->string('givenname');
            $table->string('middlename')->nullable();
            $table->string('suffix')->nullable();
            $table->string('name_of_payor');
            $table->string('or_number');
            $table->date('date_of_death');
            $table->date('date_of_payment');
            $table->string('year_covered', 11);
            $table->decimal('amount_due', 14, 2);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('rental_id');
            $table->unsignedBigInteger('status_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('rental_id')->references('id')->on('rentals')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deseased_info');
    }
};
