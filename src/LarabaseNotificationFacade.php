<?php

namespace Akhelij\LarabaseNotification;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array sendNotification(string $deviceToken, string $title, string $body, array $additionalData = [], ?LarabaseMessage $message = null)
 * @method static array sendToMultipleTokens(array $tokens, string $title, string $body, array $additionalData = [], ?LarabaseMessage $message = null)
 * @method static string getAccessToken()
 *
 * @see \Akhelij\LarabaseNotification\LarabaseNotification
 */
class LarabaseNotificationFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'larabase-notification';
    }
}
