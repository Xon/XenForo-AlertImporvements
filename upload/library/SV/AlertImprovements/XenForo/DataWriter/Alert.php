<?php

class SV_AlertImprovements_XenForo_DataWriter_Alert extends XFCP_SV_AlertImprovements_XenForo_DataWriter_Alert
{
    protected function _getFields()
    {
        $fields = parent::_getFields();
        $fields['xf_user_alert']['summerize_id'] = array('type' => self::TYPE_UNKNOWN, 'default' => null, 'verification' => array('$this', '_verifySummerizeId'));
        return $fields;
    }

    protected function _verifySummerizeId(&$summerize_id)
    {
        if ($summerize_id === null)
        {
            return true;
        }
        if (empty($summerize_id))
        {
            $summerize_id = null;
            return true;
        }
        $summerize_id = intval($summerize_id);
        return true;
    }
}
