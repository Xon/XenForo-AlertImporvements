<?php

class SV_AlertImprovements_Installer
{
    public static function install($existingAddOn, $addOnData)
    {
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

        SV_Utils_Install::addColumn('xf_user_option', 'sv_alerts_page_skips_mark_read', 'tinyint(3) unsigned NOT NULL DEFAULT 0');

        return true;
    }

    public static function uninstall()
    {
        SV_Utils_Install::dropColumn('xf_user_option', 'sv_alerts_page_skips_mark_read');

        return true;
    }
}
