<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use App\Listeners\AddUserToBrevo;
use App\Listeners\TrackUserLogin;
use App\Listeners\TrackUserLogout;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            AddUserToBrevo::class,
        ],
        Login::class => [
            TrackUserLogin::class,
        ],
        Logout::class => [
            TrackUserLogout::class,
        ],
    ];
}
