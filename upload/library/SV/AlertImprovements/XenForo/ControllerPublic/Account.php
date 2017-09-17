<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Account extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Account
{
    public function actionSummarizeAlerts()
    {
        $options = XenForo_Application::getOptions();
        if ($options->sv_alerts_summerize)
        {
            $this->assertNotFlooding('alertSummarize', max(1, intval($options->sv_alerts_summerize_flood)));
            /** @var XFCP_SV_AlertImprovements_XenForo_Model_Alert $alertModel */
            $alertModel = $this->_getAlertModel();
            $alertModel->summarizeAlertsForUser(XenForo_Visitor::getUserId());
        }
        else
        {
            return $this->getNotFoundResponse();
        }
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('account/alerts', array(), array())
        );
    }

    public function actionAlerts()
    {
        $visitor = XenForo_Visitor::getInstance()->toArray();

        $explicitMarkAsRead = isset($_REQUEST['skip_mark_read']) && empty($_REQUEST['skip_mark_read']);
        if (!empty($visitor['sv_alerts_page_skips_mark_read']) && !$explicitMarkAsRead)
        {
            $_POST['skip_mark_read'] = 1;
        }

        $explictSkipSummarize = isset($_REQUEST['skip_summarize']) && $_REQUEST['skip_summarize'];
        if (!empty($visitor['sv_alerts_page_skips_summarize']) || $explictSkipSummarize)
        {
            SV_AlertImprovements_Globals::$skipSummarize = true;
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

    public function actionAlert()
    {
        $visitor = XenForo_Visitor::getInstance()->toArray();
        /** @var XFCP_SV_AlertImprovements_XenForo_Model_Alert $alertModel */
        $alertModel = $this->_getAlertModel();

        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $alertId = $this->_input->filterSingle('alert_id', XenForo_Input::UINT);
        $skip_mark_read = $this->_input->filterSingle('skip_mark_read', XenForo_Input::UINT);

        if (!$skip_mark_read && $page == 0)
        {
            $alert = $alertModel->changeAlertStatus($visitor['user_id'], $alertId, true);
        }
        else
        {
            $alert = $alertModel->getAlertById($alertId);
            if ($alert['alerted_user_id'] != $visitor['user_id'])
            {
                $alert = false;
            }
        }
        if ($alert)
        {
            $alert = $alertModel->preparedAlertForUser($visitor['user_id'], $alert, $visitor);
        }

        if (empty($alert))
        {
            return $this->responseNoPermission();
        }

        $perPage = XenForo_Application::get('options')->alertsPerPage;
        SV_AlertImprovements_Globals::$summerizationAlerts = $alert['alert_id'];
        $alertResults = $alertModel->getAlertsForUser(
            $visitor['user_id'],
            XenForo_Model_Alert::FETCH_MODE_RECENT,
            array(
                'page' => $page,
                'perPage' => $perPage,
            )
        );

        $pageNavParams = array();
        $pageNavParams['alert_id'] = $alert['alert_id'];

        $viewParams = array(
            'summaryAlert' => $alert,
            'alerts' => $alertResults['alerts'],
            'alertHandlers' => $alertResults['alertHandlers'],

            'pageNavParams' => $pageNavParams,
            'page' => $page,
            'perPage' => $perPage,
            'totalAlerts' => $alertModel->countAlertsForUser($visitor['user_id'])
        );

        return $this->responseView('SV_AlertImprovements_ViewPublic_Account_SummaryAlerts', 'account_alerts_summary', $viewParams);
    }

    public function actionUnreadAlert()
    {
        /** @var XFCP_SV_AlertImprovements_XenForo_Model_Alert $alertModel */
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

    public function actionUnsummarizeAlert()
    {
        /** @var XFCP_SV_AlertImprovements_XenForo_Model_Alert $alertModel */
        $alertModel = $this->_getAlertModel();
        $summaryId = $this->_input->filterSingle('alert_id', XenForo_Input::UINT);

        $alertModel->insertUnsummarizedAlerts(XenForo_Visitor::getUserId(), $summaryId, false);

        $params = array(
            'skip_mark_read' => true,
            'skip_summarize' => true
        );

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('account/alerts', array(), $params)
        );
    }

    public function actionAlertPreferencesSave()
    {
        $settings = $this->_input->filter(array(
            'sv_alerts_page_skips_mark_read' => XenForo_Input::UINT,
            'sv_alerts_page_skips_summarize' => XenForo_Input::UINT,
            'sv_alerts_summarize_threshold'  => XenForo_Input::UINT
        ));

        $errors = array();
        $this->_saveVisitorSettings($settings, $errors);

        if (!empty($errors))
        {
            return $this->responseError($errors);
        }

        return parent::actionAlertPreferencesSave();
    }
}
