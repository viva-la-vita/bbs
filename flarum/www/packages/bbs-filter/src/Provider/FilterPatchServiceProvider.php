<?php

namespace VivalAvita\BbsFilter\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use FoF\Filter\Listener\CheckPost;
use VivalAvita\BbsFilter\Listener\PatchedCheckPost;

class FilterPatchServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(CheckPost::class, PatchedCheckPost::class);
    }
}
