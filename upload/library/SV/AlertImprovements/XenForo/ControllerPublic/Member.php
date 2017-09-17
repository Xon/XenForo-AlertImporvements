<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Member extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Member
{
    public function actionMember()
    {
        $response = parent::actionMember();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['profilePosts']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->getUserId() && $visitor->alerts_unread)
            {
                $alertModel = $this->_getAlertModel();
                $contentIds = XenForo_Application::arrayColumn($response->params['profilePosts'], 'profile_post_id');
                $alertModel->markAlertsAsRead('profile_post', $contentIds);
                $contentIds = [];
                foreach ($response->params['profilePosts'] as $profilePost)
                {
                    if (empty($profilePost['comments']))
                    {
                        continue;
                    }
                    foreach ($profilePost['comments'] AS $commentId => &$comment)
                    {
                        $contentIds[] = $commentId;
                    }
                }
                if ($contentIds)
                {
                    $alertModel->markAlertsAsRead('profile_post_comment', $contentIds);
                }
            }
        }

        return $response;
    }

    /**
     * @return XFCP_SV_AlertImprovements_XenForo_Model_Alert|XenForo_Model_Alert|XenForo_Model
     */
    protected function _getAlertModel()
    {
        return $this->getModelFromCache('XenForo_Model_Alert');
    }
}
