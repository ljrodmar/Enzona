<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveReturnUrlsFromEnzonaSettingsTable extends Migration
{
    public function up()
    {
        Schema::table('enzona_settings', function (Blueprint $table) {
            $table->dropColumn(['return_url', 'cancel_url']);
        });
    }

    public function down()
    {
        Schema::table('enzona_settings', function (Blueprint $table) {
            $table->text('return_url')->nullable();
            $table->text('cancel_url')->nullable();
        });
    }
}
