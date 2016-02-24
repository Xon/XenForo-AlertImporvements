<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Report extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Report
{
    public function actionView()
    {
        $response = parent::actionView();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['comments']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $contentIds = XenForo_Application::arrayColumn($response->params['comments'], 'report_comment_id');
                $this->_getAlertModel()->markAlertsAsRead('report_comment', $contentIds);
            }
        }
        return $response;
    }

    protected function _getAlertModel()
    {
        return $this->getModelFromCache('XenForo_Model_Alert');
    }
}