<?php

class SV_AlertImprovements_XenForo_Model_Alert extends XFCP_SV_AlertImprovements_XenForo_Model_Alert
{
    /**
     * @param int      $userId
     * @param int|null $time
     */
    public function markAllAlertsReadForUser($userId, $time = null)
    {
        SV_AlertImprovements_Globals::$markedAlertsRead = true;
        parent::markAllAlertsReadForUser($userId, $time);
    }

    /**
     * @return string
     */
    protected function getSummerizeSQL()
    {
        if (SV_AlertImprovements_Globals::$summerizationAlerts)
        {
            if (SV_AlertImprovements_Globals::$summerizationAlerts === true)
            {
                return ' AND summerize_id is null ';
            }
            else
            {
                return ' AND summerize_id = ' . $this->_getDb()->quote(
                        SV_AlertImprovements_Globals::$summerizationAlerts
                    ) . ' ';
            }
        }

        return '';
    }

    /**
     * @param int $userId
     * @return string
     */
    public function countAlertsForUser($userId)
    {
        $sql = $this->getSummerizeSQL();
        // need to replace the entire query...
        // *********************
        return $this->_getDb()->fetchOne(
            "
            SELECT COUNT(*)
            FROM xf_user_alert
            WHERE alerted_user_id = ? {$sql}
                AND (view_date = 0 OR view_date > ?)
        ", [$userId, $this->_getFetchModeDateCut(self::FETCH_MODE_RECENT)]
        );
        // *********************
    }

    /**
     * @param string $string
     * @param string $test
     * @return bool
     */
    function endswith($string, $test)
    {
        $strlen = strlen($string);
        $testlen = strlen($test);
        if ($testlen > $strlen)
        {
            return false;
        }

        return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
    }

    /**
     * @return XenForo_AlertHandler_Abstract[]
     */
    public function getAlertHandlers()
    {
        $handlerClasses = $this->getContentTypesWithField('alert_handler_class');
        $handlers = [];
        foreach ($handlerClasses AS $contentType => $handlerClass)
        {
            if (!$handlerClass || !class_exists($handlerClass))
            {
                continue;
            }

            $handlers[$contentType] = XenForo_AlertHandler_Abstract::create($handlerClass);
        }
        $this->_handlerCache = $handlers;

        return $handlers;
    }

    /**
     * @param SV_AlertImprovements_IConsolidateAlertHandler $handler
     * @param int                      $summarizeThreshold
     * @param string                   $contentType
     * @param int                      $contentId
     * @param array                    $alertGrouping
     * @param int                      $grouped
     * @param array                    $outputAlerts
     * @param string                   $groupingStyle
     * @param  int                     $senderUserId
     * @param  int                     $summaryAlertViewDate
     * @return bool
     */
    protected function insertSummaryAlert($handler, $summarizeThreshold, $contentType, $contentId, array $alertGrouping, &$grouped, array &$outputAlerts, $groupingStyle, $senderUserId, $summaryAlertViewDate)
    {
        $grouped = 0;
        if (!$summarizeThreshold || count($alertGrouping) < $summarizeThreshold)
        {
            return false;
        }
        $lastAlert = reset($alertGrouping);

        // inject a grouped alert with the same content type/id, but with a different action
        $summaryAlert = [
            'alerted_user_id' => $lastAlert['alerted_user_id'],
            'user_id'         => $senderUserId,
            'username'        => $senderUserId ? $lastAlert['username'] : 'Guest',
            'content_type'    => $contentType,
            'content_id'      => $contentId,
            'action'          => $lastAlert['action'] . '_summary',
            'event_date'      => $lastAlert['event_date'],
            'view_date'       => $summaryAlertViewDate,
            'extra_data'      => [],
        ];
        $summaryAlert = $handler->summarizeAlerts($summaryAlert, $alertGrouping, $groupingStyle);
        if (empty($summaryAlert))
        {
            return false;
        }
        // database update
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Alert');
        $dw->bulkSet($summaryAlert);
        $dw->save();
        $summaryAlert = $dw->getMergedData();
        // bits required for alert processing
        if ($senderUserId)
        {
            $summaryAlert['gender'] = $lastAlert['gender'];
            $summaryAlert['avatar_date'] = $lastAlert['avatar_date'];
            $summaryAlert['gravatar'] = $lastAlert['gravatar'];
        }
        else
        {
            $summaryAlert['gender'] = null;
            $summaryAlert['avatar_date'] = null;
            $summaryAlert['gravatar'] = null;
        }
        // hide the non-summary alerts
        $db = $this->_getDb();
        $stmt = $db->query(
            '
            UPDATE xf_user_alert
            SET summerize_id = ?, view_date = ?
            WHERE alert_id IN (' . $db->quote(XenForo_Application::arrayColumn($alertGrouping, 'alert_id')) . ')
        ', [$summaryAlert['alert_id'], XenForo_Application::$time]
        );
        $rowsAffected = $stmt->rowCount();
        // add to grouping
        $grouped += $rowsAffected;
        $outputAlerts[$summaryAlert['alert_id']] = $summaryAlert;

        return true;
    }

    /**
     * @param int $userId
     * @param int $summaryId
     */
    public function insertUnsummarizedAlerts($userId, $summaryId)
    {
        $db = $this->_getDb();
        XenForo_Db::beginTransaction($db);

        // Delete summary alert
        $summaryAlert = $this->getAlertById($summaryId);
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Alert', XenForo_DataWriter::ERROR_SILENT);
        if (!$dw->setExistingData($summaryAlert, true))
        {
            @XenForo_Db::rollback($db);

            return;
        }
        $dw->delete();

        // Make alerts visible
        $stmt = $db->query(
            '
            UPDATE xf_user_alert
            SET summerize_id = NULL, view_date = 0
            WHERE alerted_user_id = ? AND summerize_id = ?
        ', [$userId, $summaryId]
        );

        // Reset unread alerts counter
        $increment = $stmt->rowCount();
        $db->query(
            '
            UPDATE xf_user SET
            alerts_unread = alerts_unread + ?
            WHERE user_id = ?
                AND alerts_unread < 65535
        ', [$increment, $userId]
        );

        $visitor = XenForo_Visitor::getInstance();
        if ($visitor['user_id'] == $userId)
        {
            $visitor['alerts_unread'] += $increment;
        }

        XenForo_Db::commit($db);
    }

    /**
     * @param int $userId
     * @return bool
     */
    protected function getSummarizeLock($userId)
    {
        $db = $this->_getDb();
        if ($userId &&
            $db->fetchOne("select get_lock(?, ?)", ['alertSummarize_' . $userId, 0.01]))
        {
            return $userId;
        }

        return false;
    }

    /**
     * @param int $userId
     */
    protected function releaseSummarizeLock($userId)
    {
        if ($userId)
        {
            $db = $this->_getDb();
            $db->fetchOne("select release_lock(?)", ['alertSummarize_' . $userId]);
        }
    }

    /**
     * @param array $viewingUser
     * @return XenForo_AlertHandler_Abstract[]|SV_AlertImprovements_IConsolidateAlertHandler[]
     */
    public function getAlertHandlersForConsolidation(array $viewingUser)
    {
        $optOuts = $this->getAlertOptOuts($viewingUser);
        $handlers = $this->getAlertHandlers();
        unset($handlers['bookmark_post_alt']);
        foreach ($handlers AS $key => $handler)
        {
            /** @var SV_AlertImprovements_IConsolidateAlertHandler $handler */
            if (!is_callable([$handler, 'canSummarizeForUser']) ||
                !is_callable([$handler, 'canSummarizeItem']) ||
                !is_callable([$handler, 'consolidateAlert']) ||
                !is_callable([$handler, 'summarizeAlerts']) ||
                !$handler->canSummarizeForUser($optOuts, $viewingUser))
            {
                unset($handlers[$key]);
            }
        }

        return $handlers;
    }

    /**
     * @param int $userId
     */
    public function summarizeAlertsForUser($userId)
    {
        $db = $this->_getDb();
        // post rating summary alerts really can't me merged, so wipe all summary alerts, and then try again
        XenForo_Db::beginTransaction($db);

        $db->query(
            "
            DELETE FROM xf_user_alert
            WHERE alerted_user_id = ? AND summerize_id IS NULL AND `action` LIKE '%_summary'
        ", $userId
        );

        $db->query(
            "
            UPDATE xf_user_alert
            SET summerize_id = NULL
            WHERE alerted_user_id = ? AND summerize_id IS NOT NULL
        ", $userId
        );

        $db->query(
            "
            UPDATE xf_user
            SET alerts_unread = (SELECT count(*) FROM xf_user_alert WHERE alerted_user_id = xf_user.user_id AND view_date = 0)
            WHERE user_id = ?
        ", $userId
        );

        $fetchOptions = [
            'forceSummarize' => true, 'ignoreReadState' => true, 'summaryAlertTime' => XenForo_Application::$time
        ];
        $this->_getAlertsFromSource($userId, static::FETCH_MODE_ALL, $fetchOptions);

        XenForo_Db::commit($db);
    }

    /**
     * @param int    $userId
     * @param string $fetchMode
     * @param array  $fetchOptions
     * @return array
     */
    protected function _getAlertsFromSource($userId, $fetchMode, array $fetchOptions = [])
    {
        if ($fetchMode == self::FETCH_MODE_POPUP)
        {
            $fetchOptions['page'] = 0;
            $fetchOptions['perPage'] = 25;
        }

        $visitor = XenForo_Visitor::getInstance();
        $viewingUser = $visitor->toArray();

        $summarizeThreshold = isset($viewingUser['sv_alerts_summarize_threshold']) ? $viewingUser['sv_alerts_summarize_threshold'] : 4;
        $summarizeUnreadThreshold = $summarizeThreshold * 2 > 25 ? 25 : $summarizeThreshold * 2;
        $originalLimit = isset($fetchOptions['perPage']) ? $fetchOptions['perPage'] : 50;
        $summerizeToken = false;

        $summaryAlertViewDate = isset($fetchOptions['ignoreReadState']) ? intval($fetchOptions['summaryAlertTime']) : 0;
        $ignoreReadState = !empty($fetchOptions['ignoreReadState']);
        if (!empty($fetchOptions['forceSummarize']))
        {
            $summarizeUnreadThreshold = -1;
        }

        // determine if summarize needs to occur
        if (
            (!isset($fetchOptions['page']) || $fetchOptions['page'] == 0) &&
            $viewingUser['alerts_unread'] > $summarizeUnreadThreshold &&
            !SV_AlertImprovements_Globals::$skipSummarize &&
            XenForo_Application::getOptions()->sv_alerts_summerize)
        {
            $summerizeToken = $this->getSummarizeLock($userId);
        }
        try
        {
            if ($summerizeToken)
            {
                $fetchMode = static::FETCH_MODE_RECENT;
                $fetchOptions['page'] = 0;
                $originalLimit = isset($fetchOptions['perPage']) ? $fetchOptions['perPage'] : 0;
                unset($fetchOptions['perPage']);
            }

            // need to replace the entire query...
            //$alerts = parent::_getAlertsFromSource($userId, $fetchMode, $fetchOptions);
            // *********************

            $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

            $sql = $this->getSummerizeSQL();
            $alerts = $this->fetchAllKeyed(
                $this->limitQueryResults(
                    "
                    SELECT
                        alert.*,
                        user.gender, user.avatar_date, user.gravatar,
                        IF (user.user_id IS NULL, alert.username, user.username) AS username
                    FROM xf_user_alert AS alert
                    LEFT JOIN xf_user AS user ON
                        (user.user_id = alert.user_id)
                    WHERE alert.alerted_user_id = ? {$sql}
                        AND (alert.view_date = 0 OR alert.view_date > ?)
                    ORDER BY event_date DESC
                ", $limitOptions['limit'], $limitOptions['offset']
                ), 'alert_id', [$userId, $this->_getFetchModeDateCut($fetchMode)]
            );
            // *********************

            if (!$summerizeToken)
            {
                return $this->_filterAlertsToLimit($alerts, $originalLimit);
            }

            //$oldAlerts = $alerts;
            $outputAlerts = [];
            $db = $this->_getDb();
            // build the list of handlers at once, and exclude based
            $handlers = $this->getAlertHandlersForConsolidation($viewingUser);
            // nothing to be done
            $userHandler = empty($handlers['user']) ? null : $handlers['user'];
            if (empty($handlers) || ($userHandler && count($handlers) == 1))
            {
                return $this->_filterAlertsToLimit($alerts, $originalLimit);
            }

            // collect alerts into groupings by content/id
            $groupedContentAlerts = [];
            $groupedUserAlerts = [];
            $groupedAlerts = false;
            foreach ($alerts AS $id => $item)
            {
                if ((!$ignoreReadState && $item['view_date']) ||
                    empty($handlers[$item['content_type']]) ||
                    $this->endswith($item['action'], '_summary'))
                {
                    $outputAlerts[$id] = $item;
                    continue;
                }
                $handler = $handlers[$item['content_type']];
                if (!$handler->canSummarizeItem($item))
                {
                    $outputAlerts[$id] = $item;
                    continue;
                }

                $contentType = $item['content_type'];
                $contentId = $item['content_id'];
                if ($handler->consolidateAlert($contentType, $contentId, $item))
                {
                    $groupedContentAlerts[$contentType][$contentId][$id] = $item;

                    if ($userHandler && $userHandler->canSummarizeItem($item))
                    {
                        if (!isset($groupedUserAlerts[$item['user_id']]))
                        {
                            $groupedUserAlerts[$item['user_id']] = ['c' => 0, 'd' => []];
                        }
                        $groupedUserAlerts[$item['user_id']]['c'] += 1;
                        $groupedUserAlerts[$item['user_id']]['d'][$contentType][$contentId][$id] = $item;
                    }
                }
                else
                {
                    $outputAlerts[$id] = $item;
                }
            }

            // determine what can be summerised by content types. These require explicit support (ie a template)
            $grouped = 0;
            foreach ($groupedContentAlerts AS $contentType => &$contentIds)
            {
                $handler = $handlers[$contentType];
                foreach ($contentIds AS $contentId => $alertGrouping)
                {
                    if ($this->insertSummaryAlert(
                        $handler, $summarizeThreshold, $contentType, $contentId, $alertGrouping, $grouped,
                        $outputAlerts, 'content', 0, $summaryAlertViewDate
                    ))
                    {
                        unset($contentIds[$contentId]);
                        $groupedAlerts = true;
                    }
                }
            }
            // see if we can group some alert by user (requires deap knowledge of most content types and the template)
            if ($userHandler)
            {
                foreach ($groupedUserAlerts AS $senderUserId => &$perUserAlerts)
                {
                    if (!$summarizeThreshold || $perUserAlerts['c'] < $summarizeThreshold)
                    {
                        unset($groupedUserAlerts[$senderUserId]);
                        continue;
                    }

                    $userAlertGrouping = [];
                    foreach ($perUserAlerts['d'] AS $contentType => &$contentIds)
                    {
                        foreach ($contentIds AS $contentId => $alertGrouping)
                        {
                            foreach ($alertGrouping AS $id => $alert)
                            {
                                if (isset($groupedContentAlerts[$contentType][$contentId][$id]))
                                {
                                    $alert['content_type_map'] = $contentType;
                                    $alert['content_id_map'] = $contentId;
                                    $userAlertGrouping[$id] = $alert;
                                }
                            }
                        }
                    }
                    if ($userAlertGrouping && $this->insertSummaryAlert(
                            $userHandler, $summarizeThreshold, 'user', $userId, $userAlertGrouping, $grouped,
                            $outputAlerts, 'user', $senderUserId, $summaryAlertViewDate
                        ))
                    {
                        foreach ($userAlertGrouping AS $id => $alert)
                        {
                            unset($groupedContentAlerts[$alert['content_type_map']][$alert['content_id_map']][$id]);
                        }
                        $groupedAlerts = true;
                    }
                }
            }

            // output ungrouped alerts
            foreach ($groupedContentAlerts AS $contentType => &$contentIds)
            {
                foreach ($contentIds AS $contentId => $alertGrouping)
                {
                    foreach ($alertGrouping AS $alertId => $alert)
                    {
                        $outputAlerts[$alertId] = $alert;
                    }
                }
            }

            // update alert totals
            if ($groupedAlerts)
            {
                $sql = $this->getSummerizeSQL();
                //$visitor['alerts_unread'] = count($outputAlerts);
                $visitor['alerts_unread'] = $db->fetchOne(
                    "
                    SELECT COUNT(*)
                    FROM xf_user_alert
                    WHERE alerted_user_id = ? AND view_date = 0 {$sql}
                ", [$userId]
                );
                $db->query(
                    "
                    UPDATE xf_user
                    SET alerts_unread = ?
                    WHERE user_id = ?
                ", [$visitor['alerts_unread'], $userId]
                );
            }

            uasort(
                $outputAlerts, function ($a, $b) {
                if ($a['event_date'] == $b['event_date'])
                {
                    return ($a['alert_id'] < $b['alert_id']) ? 1 : -1;
                }

                return ($a['event_date'] < $b['event_date']) ? 1 : -1;
            }
            );
            return $this->_filterAlertsToLimit($outputAlerts, $originalLimit);
        }
        finally
        {
            $this->releaseSummarizeLock($summerizeToken);
        }
    }

    /**
     * @param array  $alerts
     * @param string $originalLimit
     * @return array
     */
    protected function _filterAlertsToLimit($alerts, $originalLimit)
    {
        // sanity check
        if ($originalLimit && count($alerts) > $originalLimit)
        {
            $alerts = array_slice($alerts, 0, $originalLimit, true);
        }

        return $alerts;
    }

    /**
     * @param string $contentType
     * @param array  $contentIds
     * @throws Zend_Db_Statement_Mysqli_Exception
     */
    public function markAlertsAsRead($contentType, array $contentIds)
    {
        if (self::PREVENT_MARK_READ || empty($contentIds))
        {
            return;
        }

        $visitor = XenForo_Visitor::getInstance();
        $userId = $visitor->getUserId();

        $db = $this->_getDb();
        // Do a select first to reduce the amount of rows that can be touched for the update.
        // This hopefully reduces contention as must of the time it should just be a select, without any updates
        $alertIds = $db->fetchCol(
            "
            SELECT alert_id
            FROM xf_user_alert
            WHERE alerted_user_id = ? AND view_date = 0 AND event_date < ? AND content_type IN(" . $db->quote(
                $contentType
            ) . ") AND content_id IN (" . $db->quote($contentIds) . ")
        ", [$userId, XenForo_Application::$time]
        );
        if (empty($alertIds))
        {
            return;
        }

        $stmt = $db->query(
            "
            UPDATE IGNORE xf_user_alert
            SET view_date = ?
            WHERE view_date = 0 AND alert_id IN (" . $db->quote($alertIds) . ")
        ", [XenForo_Application::$time]
        );
        $rowsAffected = $stmt->rowCount();

        if ($rowsAffected)
        {
            try
            {
                $db->query(
                    "
                    UPDATE xf_user
                    SET alerts_unread = GREATEST(0, cast(alerts_unread AS SIGNED) - ?)
                    WHERE user_id = ?
                ", [$rowsAffected, $userId]
                );
            }
            catch (Zend_Db_Statement_Mysqli_Exception $e)
            {
                // something went wrong, recount the alerts and return
                if (stripos(
                        $e->getMessage(), "Deadlock found when trying to get lock; try restarting transaction"
                    ) !== false)
                {
                    if (XenForo_Db::inTransaction($db))
                    {
                        // why the hell are we inside a transaction?
                        XenForo_Error::logException($e, false, 'Unexpected transaction; ');
                        $rowsAffected = 0;
                        $sql = $this->getSummerizeSQL();
                        $visitor['alerts_unread'] = $db->fetchOne(
                            "
                            SELECT COUNT(*)
                            FROM xf_user_alert
                            WHERE alerted_user_id = ? AND view_date = 0 {$sql}",
                            [$userId]
                        );
                    }
                    else
                    {
                        $db->query(
                            "
                            UPDATE xf_user
                            SET alerts_unread = GREATEST(0, cast(alerts_unread AS SIGNED) - ?)
                            WHERE user_id = ?
                        ", [$rowsAffected, $userId]
                        );
                    }
                }
                else
                {
                    throw $e;
                }
            }
            $visitor['alerts_unread'] -= $rowsAffected;
            if ($visitor['alerts_unread'] < 0)
            {
                $visitor['alerts_unread'] = 0;
            }
        }
    }

    /**
     * @param int  $userId
     * @param int  $alertId
     * @param bool $readStatus
     * @return array
     */
    public function changeAlertStatus($userId, $alertId, $readStatus)
    {
        $db = $this->_getDb();

        XenForo_Db::beginTransaction($db);

        $alert = $db->fetchRow(
            "
            SELECT *
            FROM xf_user_alert
            WHERE alerted_user_id = ? AND alert_id = ?
        ", [$userId, $alertId]
        );

        if (empty($alert) || $readStatus == ($alert['view_date'] != 0))
        {
            @XenForo_Db::rollback($db);

            return $alert;
        }

        if ($readStatus)
        {
            $db->query(
                "
                UPDATE xf_user
                SET alerts_unread = GREATEST(0, cast(alerts_unread AS SIGNED) - 1)
                WHERE user_id = ?
            ", $userId
            );

            $db->query(
                "
                UPDATE xf_user_alert
                SET view_date = ?
                WHERE alerted_user_id = ? AND alert_id = ?
            ", [XenForo_Application::$time, $userId, $alertId]
            );
        }
        else
        {
            $db->query(
                "
                UPDATE xf_user
                SET alerts_unread = LEAST(alerts_unread + 1, 65535)
                WHERE user_id = ?
            ", $userId
            );

            $db->query(
                "
                UPDATE xf_user_alert
                SET view_date = 0
                WHERE alerted_user_id = ? AND alert_id = ?
            ", [$userId, $alertId]
            );
        }

        XenForo_Db::commit($db);

        if ($readStatus)
        {
            $alert['view_date'] = XenForo_Application::$time;
            $increment = -1;
        }
        else
        {
            $alert['view_date'] = 0;
            $increment = 1;
        }

        $visitor = XenForo_Visitor::getInstance();
        if ($visitor['user_id'] == $userId)
        {
            $visitor['alerts_unread'] += $increment;
        }

        return $alert;
    }

    /**
     * @param int        $userId
     * @param array      $alert
     * @param array|null $viewingUser
     * @return mixed
     */
    public function preparedAlertForUser($userId, $alert, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $alert['gender'] = $viewingUser['gender'];
        $alert['avatar_date'] = $viewingUser['avatar_date'];
        $alert['gravatar'] = $viewingUser['gravatar'];
        $alerts = [$alert];

        $alerts = $this->_getContentForAlerts($alerts, $userId, $viewingUser);
        $alerts = $this->_getViewableAlerts($alerts, $viewingUser);

        $alerts = $this->prepareAlerts($alerts, $viewingUser);

        return reset($alerts);
    }
}
