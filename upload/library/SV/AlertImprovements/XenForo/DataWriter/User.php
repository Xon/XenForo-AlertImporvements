<?php

class SV_AlertImprovements_XenForo_DataWriter_User extends XFCP_SV_AlertImprovements_XenForo_DataWriter_User
{
    protected function _getFields()
    {
        $fields = parent::_getFields();
        $fields['xf_user_option']['sv_alerts_page_skips_mark_read'] = ['type' => self::TYPE_BOOLEAN, 'default' => 0];
        $fields['xf_user_option']['sv_alerts_page_skips_summarize'] = ['type' => self::TYPE_BOOLEAN, 'default' => 0];
        $fields['xf_user_option']['sv_alerts_summarize_threshold'] = ['type' => self::TYPE_INT, 'default' => 0];

        return $fields;
    }
}
