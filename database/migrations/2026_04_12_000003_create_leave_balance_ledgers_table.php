<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balance_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_request_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['allocation', 'deduction', 'reversal']);
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('description');
            $table->year('year');
            $table->timestamps();

            $table->index(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_ledgers');
    }
};
