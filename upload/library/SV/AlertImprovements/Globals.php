<?php

// This class is used to encapsulate global state between layers without using $GLOBAL[] or
// relying on the consumer being loaded correctly by the dynamic class autoloader
class SV_AlertImprovements_Globals
{
    public static $summerizationAlerts = true;
    public static $markedAlertsRead = false;
    public static $skipSummarize = false;

    private function __construct() {}
}