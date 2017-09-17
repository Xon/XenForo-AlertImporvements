<?php


interface SV_AlertImprovements_IConsolidateAlertHandler
{
    /**
     * @param array $optOuts
     * @param array $viewingUser
     * @return bool
     */
    public function canSummarizeForUser(array $optOuts, array $viewingUser);

    /**
     * @param array $alert
     * @return bool
     */
    public function canSummarizeItem(array $alert);

    /**
     * @param string $contentType
     * @param int    $contentId
     * @param array  $item
     * @return bool
     */
    public function consolidateAlert(&$contentType, &$contentId, array $item);

    /**
     * @param array   $summaryAlert
     * @param array   $alerts
     * @param  string $groupingStyle
     * @return array
     */
    public function summarizeAlerts(array $summaryAlert, array $alerts, $groupingStyle);
}
