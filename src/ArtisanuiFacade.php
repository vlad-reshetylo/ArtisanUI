<?php

namespace VladReshet\ArtisanUI;

use Illuminate\Support\Facades\Facade;

/**
 * @see \VladReshet\ArtisanUI\Skeleton\SkeletonClass
 */
class ArtisanUIFacade extends Facade
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
