<?php

namespace monolyth\render;

use Adapter_Access;
use Twig_LoaderInterface;

class Twig_Email implements Twig_LoaderInterface
{
    public function getSource($name)
    {
        return $name;
    }

    public function getCacheKey($name)
    {
        return md5($name);
    }

    public function isFresh($name, $time)
    {
        return false;
    }
}

