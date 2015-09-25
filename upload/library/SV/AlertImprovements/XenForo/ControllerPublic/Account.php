<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Account extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Account
{
    public function actionAlerts()
    {
        $response = parent::actionAlerts();
        if ($response instanceof XenForo_ControllerResponse_View)
        {
            $response->params['markedAlertsRead'] = SV_AlertImprovements_Globals::$markedAlertsRead;
        }

        return $response;
    }

    public function actionUnreadAlert()
    {
        $alertModel = $this->_getAlertModel();
        $alertId = $this->_input->filterSingle('alert_id', XenForo_Input::UINT);

        $alertModel->markUnread(XenForo_Visitor::getUserId(), $alertId);

        $params = array(
            'skip_mark_read' => true,
        );

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('account/alerts', array(), $params)
        );
    }
}