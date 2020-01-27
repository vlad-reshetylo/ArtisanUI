<?php

namespace VladReshet\Artisanui;

use Illuminate\Support\Facades\Facade;

/**
 * @see \VladReshet\Artisanui\Skeleton\SkeletonClass
 */
class ArtisanuiFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'artisanui';
    }
}
