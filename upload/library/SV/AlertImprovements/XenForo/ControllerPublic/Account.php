<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Account extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Account
{
    public function actionAlerts()
    {
        $visitor = XenForo_Visitor::getInstance()->toArray();

        $explicitMarkAsRead = isset($_REQUEST['skip_mark_read']) && empty($_REQUEST['skip_mark_read']);

        if (!empty($visitor['sv_alerts_page_skips_mark_read']) && !$explicitMarkAsRead)
        {
            $_POST['skip_mark_read'] = 1;
        }

        $response = parent::actionAlerts();
        if ($response instanceof XenForo_ControllerResponse_View)
        {
            $response->subView->params['markedAlertsRead'] = SV_AlertImprovements_Globals::$markedAlertsRead;
        }

        if ($explicitMarkAsRead)
        {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('account/alerts', array(), array())
            );
        }

        return $response;
    }

    public function actionUnreadAlert()
    {
        $alertModel = $this->_getAlertModel();
        $alertId = $this->_input->filterSingle('alert_id', XenForo_Input::UINT);

        $alertModel->changeAlertStatus(XenForo_Visitor::getUserId(), $alertId, false);

        $params = array(
            'skip_mark_read' => true,
        );

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('account/alerts', array(), $params)
        );
    }

    public function actionPreferencesSave()
    {
        SV_AlertImprovements_Globals::$PublicAccountController = $this;

        return parent::actionPreferencesSave();
    }
}