<?php

class SV_AlertImprovements_XenForo_DataWriter_User extends XFCP_SV_AlertImprovements_XenForo_DataWriter_User
{
    protected function _getFields()
    {
        $fields = parent::_getFields();
        $fields['xf_user_option']['sv_alerts_page_skips_mark_read'] = array('type' => self::TYPE_BOOLEAN, 'default' => 0);
        return $fields;
    }

    protected function _preSave()
    {
        if (!empty(SV_AlertImprovements_Globals::$PublicAccountController))
        {
            $input = SV_AlertImprovements_Globals::$PublicAccountController->getInput();
            $sv_alerts_page_skips_mark_read = $input->filterSingle('sv_alerts_page_skips_mark_read', XenForo_Input::UINT);
            $this->set('sv_alerts_page_skips_mark_read', $sv_alerts_page_skips_mark_read);
        }
        parent::_preSave();
    }
}
