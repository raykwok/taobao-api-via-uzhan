<?php namespace Raypower\TaobaoApiViaUzhan;

use Illuminate\Support\Facades\Facade;

class TaobaoApiViaUzhanFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'taobao-api-via-uzhan';
    }
}