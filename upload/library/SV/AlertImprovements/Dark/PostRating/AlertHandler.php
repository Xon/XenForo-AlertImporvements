<?php

class SV_AlertImprovements_Dark_PostRating_AlertHandler extends XFCP_SV_AlertImprovements_Dark_PostRating_AlertHandler implements IConsolidateAlertHandler
{
    public function canSummarizeForUser(array $optOuts, array $viewingUser)
    {
        return empty($optOuts['post_like']);
    }

    public function canSummarizeItem(array $alert)
    {
        return $alert['action'] == 'rate';
    }

    public function consolidateAlert(&$contentType, &$contentId, array $item)
    {
        switch ($contentType)
        {
            case 'postrating':
                $contentType = 'post';

                return true;
            default:
                return false;
        }
    }

    public function summarizeAlerts(array $summaryAlert, array $alerts, $groupingStyle)
    {
        return null;
    }
}
