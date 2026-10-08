<?php

use Flarum\Extend;
use VivalAvita\BbsReadstate\ServiceProvider;

return [
    (new Extend\ServiceProvider())
        ->register(ServiceProvider::class),
];
