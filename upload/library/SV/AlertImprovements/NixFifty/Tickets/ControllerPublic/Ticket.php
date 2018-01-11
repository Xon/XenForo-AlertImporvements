<?php

class SV_AlertImprovements_NixFifty_Tickets_ControllerPublic_Ticket extends XFCP_SV_AlertImprovements_NixFifty_Tickets_ControllerPublic_Ticket
{
    public function actionView()
    {
        $response = parent::actionView();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['messages']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $alertModel = $this->_getAlertModel();
                $contentIds = XenForo_Application::arrayColumn($response->params['messages'], 'message_id');
                $alertModel->markAlertsAsRead('ticket_message', $contentIds);
                $contentIds = [$response->params['ticket']['ticket_id']];
                $alertModel->markAlertsAsRead('ticket', $contentIds);
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
