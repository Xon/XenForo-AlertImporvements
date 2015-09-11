<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Thread extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Thread
{
    public function actionIndex()
    {
        $response = parent::actionIndex();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['thread']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $this->_getAlertModel()->markPostsAsRead($response->params['thread']['thread_id'], $response->params['posts']);
            }
        }
        return $response;
    }

    protected function _getAlertModel()
    {
        return $this->getModelFromCache('XenForo_Model_Alert');
    }
}