<?php

namespace App\Providers;

use App\Domain\Model\Access\Scope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        Passport::routes();
        Passport::tokensExpireIn(now()->addDays(1));

        Passport::refreshTokensExpireIn(now()->addDays(2));

        $scopes = Scope::all();
        $keyvals = array();
        foreach ($scopes as $scope){
            $keyvals[$scope->s_key] = $scope->description;
        }
        Passport::tokensCan($keyvals);
    }
}
