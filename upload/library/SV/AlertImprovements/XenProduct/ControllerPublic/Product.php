<?php

class SV_AlertImprovements_XenProduct_ControllerPublic_Product extends XFCP_SV_AlertImprovements_XenProduct_ControllerPublic_Product
{
    public function actionDetails()
    {
        $response = parent::actionDetails();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['version']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $alertModel = $this->_getAlertModel();
                $contentIds = [$response->params['version']['product_version_id']];
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
