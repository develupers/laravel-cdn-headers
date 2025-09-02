<?php

namespace Develupers\CdnHeaders\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Develupers\CdnHeaders\CdnHeaders
 */
class CdnHeaders extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Develupers\CdnHeaders\CdnHeaders::class;
    }
}
