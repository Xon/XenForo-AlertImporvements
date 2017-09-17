<?php

class SV_AlertImprovements_Listener
{
    public static function load_class($class, array &$extend)
    {
        $extend[] = 'SV_AlertImprovements_' . $class;
    }
}
