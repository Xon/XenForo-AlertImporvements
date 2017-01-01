<?php

class SV_AlertImprovements_XenForo_AlertHandler_DiscussionMessage_Post extends XFCP_SV_AlertImprovements_XenForo_AlertHandler_DiscussionMessage_Post
{
    public function canSummarize(array $alert)
    {
        return $alert['action'] == 'like';
    }

    public function summarizeAlerts(array $summaryAlert, array $alerts, $groupingStyle)
    {
        if ($groupingStyle != 'content')
        {
            return null;
        }

        $summaryAlert['extra_data']['likes'] = count($alerts);
        return $summaryAlert;
    }
}