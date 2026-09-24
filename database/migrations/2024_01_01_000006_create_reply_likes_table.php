<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reply_likes', function (Blueprint $table) {
            $table->foreignId('reply_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // 同じ人が同じリプライに2回いいねできないよう、組み合わせを主キーにする
            $table->primary(['reply_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reply_likes');
    }
};
