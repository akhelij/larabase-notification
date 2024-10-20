<?php

namespace Akhelij\LarabaseNotification;

use Illuminate\Support\Facades\Facade;

class LarabaseNotificationFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'larabase-notification'; // This ties to the binding in the ServiceProvider
    }
}
