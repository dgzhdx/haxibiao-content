<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCollectablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('collectables')) {
            Schema::drop('collectables');
        }
        Schema::create('collectables', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('collection_id')->unsigned();
            $table->morphs('collectable');
            $table->string('collection_name');
             //post在集合中的排序
            $table->unsignedInteger('sort_rank')->nullable()->index()->comment('排序(置顶方法)');


            //索引字段
            $table->unique(['collection_id', 'collectable_id', 'collectable_type'], 'collectable_unique');
            //$table->index('user_id');
            $table->index('collection_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('collectable');
    }
}
