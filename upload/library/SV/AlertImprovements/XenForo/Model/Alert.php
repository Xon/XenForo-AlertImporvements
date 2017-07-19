<?php

class SV_AlertImprovements_XenForo_Model_Alert extends XFCP_SV_AlertImprovements_XenForo_Model_Alert
{
    public function markAllAlertsReadForUser($userId, $time = null)
    {
        SV_AlertImprovements_Globals::$markedAlertsRead = true;
        parent::markAllAlertsReadForUser($userId, $time);
    }

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
                return ' AND summerize_id = ' . $this->_getDb()->quote(SV_AlertImprovements_Globals::$summerizationAlerts).' ';
            }
        }
        return '';
    }

    public function countAlertsForUser($userId)
    {
        // need to replace the entire query...
        // *********************
        return $this->_getDb()->fetchOne('
            SELECT COUNT(*)
            FROM xf_user_alert
            WHERE alerted_user_id = ? '. $this->getSummerizeSQL() .'
                AND (view_date = 0 OR view_date > ?)
        ', array($userId, $this->_getFetchModeDateCut(self::FETCH_MODE_RECENT)));
        // *********************
    }

    function endswith($string, $test)
    {
        $strlen = strlen($string);
        $testlen = strlen($test);
        if ($testlen > $strlen) return false;
        return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
    }

    public function getAlertHandlers()
    {
        $handlerClasses = $this->getContentTypesWithField('alert_handler_class');
        $handlers = array();
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

    protected function insertSummaryAlert($handler, $summarizeThreshold, $contentType, $contentId, array $alertGrouping, &$grouped, array &$outputAlerts, $groupingStyle, $senderUserId)
    {
        $grouped = 0;
        if (!$summarizeThreshold || count($alertGrouping) < $summarizeThreshold)
        {
            return false;
        }
        $lastAlert = reset($alertGrouping);

        // inject a grouped alert with the same content type/id, but with a different action
        $summaryAlert = array(
            'alerted_user_id' => $lastAlert['alerted_user_id'],
            'user_id' => $senderUserId,
            'username' => $senderUserId ? $lastAlert['username'] : 'Guest',
            'content_type' => $contentType,
            'content_id' => $contentId,
            'action' => $lastAlert['action'].'_summary',
            'event_date' => $lastAlert['event_date'],
            'view_date'  => 0,
            'extra_data' => array(),
        );
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
        $stmt = $db->query('
            UPDATE xf_user_alert
            SET summerize_id = ?, view_date = ?
            WHERE alert_id in (' . $db->quote(XenForo_Application::arrayColumn($alertGrouping, 'alert_id')). ')
        ', array($summaryAlert['alert_id'], XenForo_Application::$time));
        $rowsAffected = $stmt->rowCount();
        // add to grouping
        $grouped += $rowsAffected;
        $outputAlerts[$summaryAlert['alert_id']] = $summaryAlert;
        return true;
    }

    public function insertUnsummarizedAlerts($userId, $summaryId)
    {
        // Make alerts visible
        $db = $this->_getDb();
        $stmt = $db->query('
            UPDATE xf_user_alert
            SET summerize_id = null, view_date = 0
            WHERE alerted_user_id = ? and summerize_id = ?
        ', array($userId, $summaryId));

        // Reset unread alerts counter
        $increment = $stmt->rowCount();
        $this->_db->query('
            UPDATE xf_user SET
            alerts_unread = alerts_unread + ?
            WHERE user_id = ?
                AND alerts_unread < 65535
        ', array($increment, $userId));

        $visitor = XenForo_Visitor::getInstance();
        if ($visitor['user_id'] == $userId)
        {
            $visitor['alerts_unread'] += $increment;
        }

        // Delete summary alert
        $summaryAlert = $this->getAlertById($summaryId);
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Alert');
        $dw->setExistingData($summaryAlert, true);
        $dw->delete();
    }

    protected function getSummarizeLock($userId)
    {
        $db = $this->_getDb();
        if ($userId &&
            $db->fetchOne("select get_lock(?, ?)", array('alertSummarize_'.$userId, 0.01)))
        {
            return $userId;
        }
        return false;
    }

    protected function releaseSummarizeLock($userId)
    {
        if ($userId)
        {
            $db = $this->_getDb();
            $db->fetchOne("select release_lock(?)", array('alertSummarize_'.$userId));
        }
    }

    public function getAlertHandlersForConsolidation(array $viewingUser)
    {
        $optOuts = $this->getAlertOptOuts($viewingUser);
        $handlers = $this->getAlertHandlers();
        unset($handlers['bookmark_post_alt']);
        foreach ($handlers AS $key => $handler)
        {
            if (!is_callable(array($handler, 'canSummarizeForUser')) ||
                !is_callable(array($handler, 'canSummarizeItem')) ||
                !is_callable(array($handler, 'consolidateAlert')) ||
                !is_callable(array($handler, 'summarizeAlerts')) ||
                !$handler->canSummarizeForUser($optOuts, $viewingUser))
            {
                unset($handlers[$key]);
            }
        }
        return $handlers;
    }

    protected function _getAlertsFromSource($userId, $fetchMode, array $fetchOptions = array())
    {
        $this->standardizeViewingUserReference($viewingUser);

        $summarizeThreshold = isset($visitor['sv_alerts_summarize_threshold']) ? $visitor['sv_alerts_summarize_threshold'] : 4;
        $summarizeUnreadThreshold = $summarizeThreshold * 2 > 25 ? 25 : $summarizeThreshold * 2;
        $originalLimit = 0;
        $summerizeToken = false;

        // determine if summarize needs to occur
        if (($fetchMode == static::FETCH_MODE_POPUP || $fetchMode == static::FETCH_MODE_RECENT) &&
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
                $originalLimit = isset($fetchOptions['perPage']) ? $fetchOptions['perPage'] : 25;
                unset($fetchOptions['perPage']);
            }

            // need to replace the entire query...
            //$alerts = parent::_getAlertsFromSource($userId, $fetchMode, $fetchOptions);
            // *********************
            if ($fetchMode == self::FETCH_MODE_POPUP)
            {
                $fetchOptions['page'] = 0;
                $fetchOptions['perPage'] = 25;
            }

            $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

            $alerts = $this->fetchAllKeyed($this->limitQueryResults(
                '
                    SELECT
                        alert.*,
                        user.gender, user.avatar_date, user.gravatar,
                        IF (user.user_id IS NULL, alert.username, user.username) AS username
                    FROM xf_user_alert AS alert
                    LEFT JOIN xf_user AS user ON
                        (user.user_id = alert.user_id)
                    WHERE alert.alerted_user_id = ? '. $this->getSummerizeSQL() .'
                        AND (alert.view_date = 0 OR alert.view_date > ?)
                    ORDER BY event_date DESC
                ', $limitOptions['limit'], $limitOptions['offset']
            ), 'alert_id', array($userId, $this->_getFetchModeDateCut($fetchMode)));
            // *********************

            if (!$summerizeToken)
            {
                return $alerts;
            }

            $oldAlerts = $alerts;
            $outputAlerts = array();
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
            $groupedContentAlerts = array();
            $groupedUserAlerts = array();
            $groupedAlerts = false;
            foreach ($alerts AS $id => $item)
            {
                if ($item['view_date'] ||
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
                            $groupedUserAlerts[$item['user_id']] = array('c' => 0, 'd' => array());
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
                    if ($this->insertSummaryAlert($handler, $summarizeThreshold, $contentType, $contentId, $alertGrouping, $grouped, $outputAlerts, 'content', 0))
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

                    $userAlertGrouping = array();
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
                    if ($userAlertGrouping && $this->insertSummaryAlert($userHandler, $summarizeThreshold, 'user', $userId, $userAlertGrouping, $grouped, $outputAlerts, 'user', $senderUserId))
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
                $visitor = XenForo_Visitor::getInstance();
                //$visitor['alerts_unread'] = count($outputAlerts);
                $visitor['alerts_unread'] = $db->fetchOne('
                    SELECT COUNT(*)
                    FROM xf_user_alert
                    WHERE alerted_user_id = ? AND view_date = 0 '. $this->getSummerizeSQL() .'
                ', array($userId));
                $db->query("
                    update xf_user
                    set alerts_unread = ?
                    where user_id = ?
                ", array($visitor['alerts_unread'], $userId));
            }

            uasort($outputAlerts, function($a, $b) {
                if ($a['event_date'] == $b['event_date'])
                {
                    return ($a['alert_id'] < $b['alert_id']) ? 1 : -1;
                }
                return ($a['event_date'] < $b['event_date']) ? 1 : -1;
            });
            $alerts = $this->_filterAlertsToLimit($outputAlerts, $originalLimit);
        }
        finally
        {
            $this->releaseSummarizeLock($summerizeToken);
        }

        return $alerts;
    }

    protected function _filterAlertsToLimit($alerts, $originalLimit)
    {
        // sanity check
        if ($originalLimit && count($alerts) > $originalLimit)
        {
            $alerts = array_slice($alerts, 0, $originalLimit, true);
        }
        return $alerts;
    }

    public function markAlertsAsRead($contentType, array $contentIds)
    {
        if (self::PREVENT_MARK_READ || empty($contentIds))
        {
            return;
        }

        $visitor = XenForo_Visitor::getInstance();
        $userId = $visitor->getUserId();

        $db = $this->_getDb();
        $options = XenForo_Application::getOptions();
        // Do a select first to reduce the amount of rows that can be touched for the update.
        // This hopefully reduces contention as must of the time it should just be a select, without any updates
        $alertIds = $db->fetchCol("
            select alert_id
            from xf_user_alert
            where alerted_user_id = ? and view_date = 0 and event_date < ? and content_type in(". $db->quote($contentType) .") and content_id in (". $db->quote($contentIds) .")
        ", array($userId, XenForo_Application::$time));
        if (empty($alertIds))
        {
            return;
        }

        $stmt = $db->query("
            update ignore xf_user_alert
            set view_date = ?
            where view_date = 0 and alert_id in (". $db->quote($alertIds) .")
        ", array(XenForo_Application::$time));
        $rowsAffected = $stmt->rowCount();

        if ($rowsAffected)
        {
            try
            {
                $db->query("
                    update xf_user
                    set alerts_unread = GREATEST(0, cast(alerts_unread as signed) - ?)
                    where user_id = ?
                ", array($rowsAffected, $userId));
            }
            catch(Zend_Db_Statement_Mysqli_Exception $e)
            {
                // something went wrong, recount the alerts and return
                if (stripos($e->getMessage(), "Deadlock found when trying to get lock; try restarting transaction") !== false)
                {
                    if (XenForo_Db::inTransaction($db))
                    {
                        // why the hell are we inside a transaction?
                        XenForo_Error::logException($e, false, 'Unexpected transaction; ');
                        $rowsAffected = 0;
                        $visitor['alerts_unread'] = $db->fetchOne('
                            SELECT COUNT(*)
                            FROM xf_user_alert
                            WHERE alerted_user_id = ? AND view_date = 0 '. $this->getSummerizeSQL(),
                        array($userId));
                    }
                    else
                    {
                        $db->query("
                            update xf_user
                            set alerts_unread = GREATEST(0, cast(alerts_unread as signed) - ?)
                            where user_id = ?
                        ", array($rowsAffected, $userId));
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

    public function changeAlertStatus($userId, $alertId, $readStatus)
    {
        $db = $this->_getDb();

        XenForo_Db::beginTransaction($db);

        $alert = $db->fetchRow("
            SELECT *
            FROM xf_user_alert
            where alerted_user_id = ? and alert_id = ?
        ", array($userId, $alertId));

        if (empty($alert) || $readStatus == ($alert['view_date'] != 0))
        {
            @XenForo_Db::rollback($db);
            return $alert;
        }

        if ($readStatus)
        {
            $db->query("
                update xf_user
                set alerts_unread = GREATEST(0, cast(alerts_unread as signed) - 1)
                where user_id = ?
            ", $userId);

            $db->query("
                update xf_user_alert
                set view_date = ?
                where alerted_user_id = ? and alert_id = ?
            ", array(XenForo_Application::$time, $userId, $alertId));
        }
        else
        {
            $db->query("
                update xf_user
                set alerts_unread = LEAST(alerts_unread + 1, 65535)
                where user_id = ?
            ", $userId);

            $db->query("
                update xf_user_alert
                set view_date = 0
                where alerted_user_id = ? and alert_id = ?
            ", array($userId, $alertId));
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

    public function preparedAlertForUser($userId, $alert, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $alert['gender'] = $viewingUser['gender'];
        $alert['avatar_date'] = $viewingUser['avatar_date'];
        $alert['gravatar'] = $viewingUser['gravatar'];
        $alerts = array($alert);

        $alerts = $this->_getContentForAlerts($alerts, $userId, $viewingUser);
        $alerts = $this->_getViewableAlerts($alerts, $viewingUser);

        $alerts = $this->prepareAlerts($alerts, $viewingUser);

        return reset($alerts);
    }
}