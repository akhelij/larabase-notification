<?php

namespace Akhelij\LarabaseNotification;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Akhelij\LarabaseNotification\Skeleton\SkeletonClass
 */
class LarabaseNotificationFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'larabase-notification';
    }
}
