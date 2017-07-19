<?php

class SV_AlertImprovements_XenForo_AlertHandler_DiscussionMessage_Post extends XFCP_SV_AlertImprovements_XenForo_AlertHandler_DiscussionMessage_Post
{
    public function canSummarizeForUser(array $optOuts, array $viewingUser)
    {
        return empty($optOuts['post_like']);
    }

    public function canSummarizeItem(array $alert)
    {
        return $alert['action'] == 'like';
    }

    public function consolidateAlert(&$contentType, &$contentId, array $item)
    {
        switch($contentType)
        {
            case 'post':
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
        $summaryAlert['action'] = isset($summaryAlert['extra_data']['ratings'])
                                  ? 'rate_summary'
                                  : 'like_summary';

        return $summaryAlert;
    }

    protected function _prepareRate_summary(array $item, array $viewingUser)
    {
        if (is_callable('parent::_prepareRate_summary'))
        {
            $item = parent::_prepareRate_summary($item, $viewingUser);
        }

        $item["isSummary"] = true;

        return SV_AlertImprovements_Helper::prepareRateSummary($item, $viewingUser);
    }

    protected function _prepareLike_summary(array $item, array $viewingUser)
    {
        if (is_callable('parent::_prepareLike_summary'))
        {
            $item = parent::_prepareLike_summary($item, $viewingUser);
        }

        $item["isSummary"] = true;

        return SV_AlertImprovements_Helper::prepareRateSummary($item, $viewingUser);
    }
}