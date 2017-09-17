<?php

class SV_AlertImprovements_SV_ReportImprovements_AlertHandler_ReportComment extends XFCP_SV_AlertImprovements_SV_ReportImprovements_AlertHandler_ReportComment implements IConsolidateAlertHandler
{
    public function canSummarizeForUser(array $optOuts, array $viewingUser)
    {
        return empty($optOuts['report_comment_like']);
    }

    public function canSummarizeItem(array $alert)
    {
        return $alert['action'] == 'like';
    }

    public function consolidateAlert(&$contentType, &$contentId, array $item)
    {
        switch ($contentType)
        {
            case 'report_comment':
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

        $summaryAlert['extra_data']['likes']['post'] = count($alerts);
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
