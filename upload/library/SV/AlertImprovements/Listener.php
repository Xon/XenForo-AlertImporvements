<?php

class SV_AlertImprovements_Listener
{
    const AddonNameSpace = 'SV_AlertImprovements_';

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }
}
