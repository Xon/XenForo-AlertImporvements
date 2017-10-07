<?php

class SV_AlertImprovements_XenProduct_ControllerPublic_License extends XFCP_SV_AlertImprovements_XenProduct_ControllerPublic_License
{
    public function actionIndex()
    {
        $response = parent::actionIndex();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['licenses']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $alertModel = $this->_getAlertModel();
                $contentIds = XenForo_Application::arrayColumn($response->params['licenses'], 'product_version_id');
                $contentType = 'xenproduct_version';
                $alertModel->markAlertsAsRead($contentType, $contentIds);
            }
        }

        return $response;
    }
    
    public function actionView()
    {
        $response = parent::actionView();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['license']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $alertModel = $this->_getAlertModel();
                $contentIds = [$response->params['license']['product_version_id']];
                $contentType = 'xenproduct_version';
                $alertModel->markAlertsAsRead($contentType, $contentIds);
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
