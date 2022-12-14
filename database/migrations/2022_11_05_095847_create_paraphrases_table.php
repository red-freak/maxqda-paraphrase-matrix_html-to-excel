<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('paraphrases', function (Blueprint $table) {
            $table->id();
            $table->string('interview_id');
            $table->foreignId('editor_id');
            $table->text('paraphrase');
            $table->integer('position_start');
            $table->integer('position_end');

            $table->foreign('interview_id')->on('interviews')->references('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('paraphrase');
    }
};
