<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cedulas', function (Blueprint $table) {
            $table->id();
            // CCI2023 serial number: typed in and saved, never printed. Unique, so the same cedula is not saved twice.
            $table->string('serial', 30)->nullable()->unique();
            $table->string('place_of_issue')->nullable();
            $table->date('date_issued')->index();

            $table->string('surname')->index();
            $table->string('first_name')->index();
            $table->string('middle_name')->nullable();
            $table->string('tin', 20)->nullable()->index();
            $table->string('address')->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('civil', 20)->nullable();
            $table->string('citizenship')->default('Filipino');
            $table->string('icr_no', 30)->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('height', 10)->nullable();
            $table->string('weight', 10)->nullable();

            $table->decimal('basic_tax_due', 10, 2)->default(5);
            $table->decimal('additional_tax_due', 10, 2)->nullable();
            $table->decimal('item1_due', 10, 2)->nullable();
            $table->decimal('item2_due', 10, 2)->nullable();
            $table->decimal('item3_due', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->decimal('interest', 10, 2)->nullable();
            $table->decimal('total_paid', 10, 2)->nullable();
            $table->string('in_words')->nullable();

            $table->string('treasurer')->nullable();

            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cedulas');
    }
};
