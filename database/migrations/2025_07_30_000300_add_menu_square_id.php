<?php

namespace CupNoodles\SquareLineItems\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMenuSquareId extends Migration
{
    public function up()
    {

        // This column are duplicates of those in the related but not identical square inventory extension
        // Make sure it's wrapped in !hasColumn etc to prevent errors during migration. 
        if(!Schema::hasColumn('menus', 'square_item_id')){
            Schema::table('menus', function (Blueprint $table) {
                $table->text('square_item_id')->nullable();
            });
        }


    }

    public function down()
    {
        if(Schema::hasColumn('menus', 'square_item_id')){
            Schema::table('menus', function (Blueprint $table) {
                $table->dropColumn('square_item_id');
            });
        }


    }
}
