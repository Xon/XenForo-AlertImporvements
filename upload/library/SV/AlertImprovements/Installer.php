<?php

class SV_AlertImprovements_Installer
{
    public static function install($existingAddOn, $addOnData)
    {
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

        self::addColumn('xf_user_option', 'sv_alerts_page_skips_mark_read', 'tinyint(3) unsigned NOT NULL DEFAULT 0');

        return true;
    }

    public static function uninstall()
    {
        self::dropColumn('xf_user_option', 'sv_alerts_page_skips_mark_read');

        return true;
    }

    public static function dropColumn($table, $column)
    {
        $db = XenForo_Application::get('db');
        if ($db->fetchRow('SHOW COLUMNS FROM `'.$table.'` WHERE Field = ?', $column))
        {
            $db->query('ALTER TABLE `'.$table.'` drop COLUMN `'.$column.'` ');
            return true;
        }
        return false;
    }

    public static function addColumn($table, $column, $definition)
    {
        $db = XenForo_Application::get('db');
        if (!$db->fetchRow('SHOW COLUMNS FROM `'.$table.'` WHERE Field = ?', $column))
        {
            $db->query('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
            return true;
        }
        return false;
    }
}
