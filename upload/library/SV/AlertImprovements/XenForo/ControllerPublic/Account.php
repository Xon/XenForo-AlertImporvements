<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Account extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Account
{
    public function actionAlerts()
    {
        $visitor = XenForo_Visitor::getInstance()->toArray();
        if (!empty($visitor['sv_alerts_page_skips_mark_read']))
        {
            $_POST['skip_mark_read'] = 1;
        }

        $response = parent::actionAlerts();
        if ($response instanceof XenForo_ControllerResponse_View)
        {
            $response->subView->params['markedAlertsRead'] = SV_AlertImprovements_Globals::$markedAlertsRead;
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

    public function actionAlertPreferencesSave()
    {
        SV_AlertImprovements_Globals::$PublicAccountController = $this;

        return parent::actionAlertPreferencesSave();
    }
}