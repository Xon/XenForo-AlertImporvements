<?php

class SV_AlertImprovements_XenForo_AlertHandler_DiscussionMessage_ProfilePost extends XFCP_SV_AlertImprovements_XenForo_AlertHandler_DiscussionMessage_ProfilePost
{
    public function canSummarizeForUser(array $optOuts, array $viewingUser)
    {
        return empty($optOuts['profile_post_like']);
    }

    public function canSummarizeItem(array $alert)
    {
        return $alert['action'] == 'like';
    }

    public function consolidateAlert(&$contentType, &$contentId, array $item)
    {
        switch ($contentType)
        {
            case 'profile_post':
                return true;
            default:
                return false;
        }
    }

    public function summarizeAlerts(array $summaryAlert, array $alerts, $groupingStyle)
    {
        if ($groupingStyle != 'content')
        {
            return null;
        }

        $summaryAlert = SV_AlertImprovements_Helper::getSummaryAlertLikeRatings($summaryAlert, $alerts);
        $summaryAlert['action'] = 'like_summary';

        return $summaryAlert;
    }

    protected function _prepareLike_summary(array $item, array $viewingUser)
    {
        if (is_callable('parent::_prepareLike_summary'))
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $item = parent::_prepareLike_summary($item, $viewingUser);
        }

        $item["isSummary"] = true;

        return SV_AlertImprovements_Helper::prepareRateSummary($item, $viewingUser);
    }
}
