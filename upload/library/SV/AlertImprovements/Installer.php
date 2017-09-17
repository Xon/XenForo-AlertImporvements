<?php

class SV_AlertImprovements_Installer
{
    public static function install($existingAddOn, $addOnData)
    {
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

        SV_Utils_Install::addColumn(
            'xf_user_option', 'sv_alerts_page_skips_mark_read', 'tinyint(3) unsigned NOT NULL DEFAULT 0'
        );
        SV_Utils_Install::addColumn(
            'xf_user_option', 'sv_alerts_page_skips_summarize', 'tinyint(3) unsigned NOT NULL DEFAULT 0'
        );
        SV_Utils_Install::addColumn(
            'xf_user_option', 'sv_alerts_summarize_threshold', 'int(10) unsigned NOT NULL DEFAULT 4'
        );
        SV_Utils_Install::addColumn('xf_user_alert', 'summerize_id', 'int(10) unsigned DEFAULT NULL');

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Option');//, XenForo_DataWriter::ERROR_SILENT);
        if ($dw->setExistingData('registrationDefaults'))
        {
            $registrationDefaults = XenForo_Application::getOptions()->registrationDefaults;
            if (!isset($registrationDefaults['sv_alerts_page_skips_mark_read']))
            {
                $registrationDefaults['sv_alerts_page_skips_mark_read'] = 0;
            }
            if (!isset($registrationDefaults['sv_alerts_page_skips_summarize']))
            {
                $registrationDefaults['sv_alerts_page_skips_summarize'] = 0;
            }
            if (!isset($registrationDefaults['sv_alerts_summarize_threshold']))
            {
                $registrationDefaults['sv_alerts_summarize_threshold'] = 4;
            }
            $dw->set('option_value', $registrationDefaults);
            if ($dw->hasChanges())
            {
                $dw->save();
            }
        }

        return true;
    }

    public static function uninstall()
    {
        SV_Utils_Install::dropColumn('xf_user_option', 'sv_alerts_page_skips_mark_read');
        SV_Utils_Install::dropColumn('xf_user_option', 'sv_alerts_page_skips_summarize');
        SV_Utils_Install::dropColumn('xf_user_option', 'sv_alerts_summarize_threshold');
        SV_Utils_Install::dropColumn('xf_user_alert', 'summerize_id');

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Option', XenForo_DataWriter::ERROR_SILENT);
        if ($dw->setExistingData('registrationDefaults'))
        {
            $registrationDefaults = XenForo_Application::getOptions()->registrationDefaults;
            unset($registrationDefaults['sv_alerts_page_skips_mark_read']);
            unset($registrationDefaults['sv_alerts_page_skips_summarize']);
            unset($registrationDefaults['sv_alerts_summarize_threshold']);
            $dw->set('option_value', $registrationDefaults);
            $dw->save();
        }

        return true;
    }
}
