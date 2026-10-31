<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('userid')->nullable()->unique();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('gender')->nullable();
            $table->string('photo')->nullable();
            $table->text('country')->nullable();
            $table->string('cityofresidency')->nullable();
            $table->string('interestcenter')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('enablement')->default(true);
            $table->string('accepted_by')->nullable();
            $table->string('password');
            $table->text('commenttotheregistration')->nullable();
            $table->boolean('mustresetpassword')->default(false);
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->boolean('isconnected')->default(false);
            $table->text('access_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
