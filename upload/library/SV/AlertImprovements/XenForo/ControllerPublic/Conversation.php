<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Conversation extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Conversation
{
    public function actionView()
    {
        $response = parent::actionView();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['messages']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $contentIds = XenForo_Application::arrayColumn($response->params['messages'], 'message_id');
                $this->_getAlertModel()->markAlertsAsRead('conversation_message', $contentIds);
                $contentId = $response->params['conversation']['conversation_id'];
                $this->_getAlertModel()->markAlertsAsRead('conversation', $contentId);
            }
        }

        return $response;
    }

    public function actionInsertReply()
    {
        $response = parent::actionInsertReply();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['messages']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $contentIds = XenForo_Application::arrayColumn($response->params['messages'], 'message_id');
                $this->_getAlertModel()->markAlertsAsRead('conversation_message', $contentIds);
            }
        }

        return $response;
    }

    /**
     * @return SV_AlertImprovements_XenForo_Model_Alert|XenForo_Model_Alert|XenForo_Model
     */
    protected function _getAlertModel()
    {
        return $this->getModelFromCache('XenForo_Model_Alert');
    }
}
