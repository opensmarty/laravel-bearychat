<?php

namespace ElfSundae\BearyChat\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ElfSundae\BearyChat\Laravel\ClientManager
 */
class BearyChat extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'bearychat';
    }
}
