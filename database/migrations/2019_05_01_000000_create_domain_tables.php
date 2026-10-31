<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDomainTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Applications
        Schema::create('applications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('applicationid')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('gender');
            $table->string('photo')->nullable();
            $table->text('country');
            $table->string('cityofresidency');
            $table->string('interestcenter');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('commenttotheregistration')->nullable();
            $table->string('state')->default('APPLICATION_RECEIVED');
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->timestamps();
        });

        // Groups
        Schema::create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('groupid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('members')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        // Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('roleid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->text('groupsplayingrole')->nullable();
            $table->text('usersplayingrole')->nullable();
            $table->text('scopes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        // Scopes
        Schema::create('scopes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('s_key')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Administrator propositions
        Schema::create('administratorpropositions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('administratorpropositionid')->unique();
            $table->string('proposeduserid');
            $table->string('proposed_by');
            $table->text('administratorssignatures')->nullable();
            $table->string('state')->default('CREATED');
            $table->timestamps();
        });

        // User revokation propositions
        Schema::create('userrevokationpropositions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('userrevokationpropositionid')->unique();
            $table->string('proposeduserid');
            $table->string('proposed_by');
            $table->text('administratorssignatures')->nullable();
            $table->string('state')->default('CREATED');
            $table->timestamps();
        });

        // Revoked users
        Schema::create('revokedusers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('revokeduserid')->unique();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('gender')->nullable();
            $table->string('photo')->nullable();
            $table->text('country')->nullable();
            $table->string('cityofresidency')->nullable();
            $table->string('interestcenter')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('enablement')->default(false);
            $table->string('revoked_by')->nullable();
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

        // Password reset invitations
        Schema::create('password_reset_invitations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('invitationid')->unique();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email');
            $table->boolean('used')->default(false);
            $table->text('url')->nullable();
            $table->timestamps();
        });

        // Interest centers
        Schema::create('interestcenters', function (Blueprint $table) {
            $table->increments('id');
            $table->string('interestcenterid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // City of residencies
        Schema::create('cityofresidencies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cityofresidencyid')->unique();
            $table->string('name');
            $table->string('countrycode')->nullable();
            $table->timestamps();
        });

        // Countries
        Schema::create('countries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('name');
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
        Schema::dropIfExists('countries');
        Schema::dropIfExists('cityofresidencies');
        Schema::dropIfExists('interestcenters');
        Schema::dropIfExists('password_reset_invitations');
        Schema::dropIfExists('revokedusers');
        Schema::dropIfExists('userrevokationpropositions');
        Schema::dropIfExists('administratorpropositions');
        Schema::dropIfExists('scopes');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('applications');
    }
}
