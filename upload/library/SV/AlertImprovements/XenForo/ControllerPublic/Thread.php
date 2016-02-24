<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Thread extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Thread
{
    public function actionIndex()
    {
        $response = parent::actionIndex();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['posts']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $alertModel = $this->_getAlertModel();
                $contentIds = XenForo_Application::arrayColumn($response->params['posts'], 'post_id');
                $contentType = class_exists('Dark_PostRating_Model_Alert', false)
                               ? array('post','postrating')
                               : 'post';
                $alertModel->markAlertsAsRead($contentType, $contentIds);
            }
        }
        return $response;
    }

    public function actionShowNewPosts()
    {
        $response = parent::actionShowNewPosts();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['posts']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $alertModel = $this->_getAlertModel();
                $contentIds = XenForo_Application::arrayColumn($response->params['posts'], 'post_id');
                $contentType = class_exists('Dark_PostRating_Model_Alert', false)
                               ? array('post','postrating')
                               : 'post';
                $alertModel->markAlertsAsRead($contentType, $contentIds);
            }
        }
        return $response;
    }

    public function actionAddReply()
    {
        $response = parent::actionAddReply();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['posts']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $alertModel = $this->_getAlertModel();
                $contentIds = XenForo_Application::arrayColumn($response->params['posts'], 'post_id');
                $contentType = class_exists('Dark_PostRating_Model_Alert', false)
                               ? array('post','postrating')
                               : 'post';
                $alertModel->markAlertsAsRead($contentType, $contentIds);
            }
        }
        return $response;
    }

    protected function _getAlertModel()
    {
        return $this->getModelFromCache('XenForo_Model_Alert');
    }
}