<?php

use Flarum\Extend;
use VivalAvita\BbsFilter\Provider\FilterPatchServiceProvider;

return [
    (new Extend\ServiceProvider())
        ->register(FilterPatchServiceProvider::class),
];
