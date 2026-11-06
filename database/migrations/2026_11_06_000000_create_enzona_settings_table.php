<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEnzonaSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('enzona_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('environment', 20)->default('sandbox');
            $table->boolean('is_enabled')->default(false);
            $table->text('consumer_key');
            $table->text('consumer_secret');
            $table->text('return_url')->nullable();
            $table->text('cancel_url')->nullable();
            $table->timestamp('token_verified_at')->nullable();
            $table->unsignedInteger('token_expires_in')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('enzona_settings');
    }
}
