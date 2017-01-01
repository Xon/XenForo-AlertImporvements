<?php

class SV_AlertImprovements_XenForo_Model_Alert extends XFCP_SV_AlertImprovements_XenForo_Model_Alert
{
    public function markAllAlertsReadForUser($userId, $time = null)
    {
        SV_AlertImprovements_Globals::$markedAlertsRead = true;
        parent::markAllAlertsReadForUser($userId, $time);
    }

    public function countAlertsForUser($userId)
    {
        // need to replace the entire query...
        $summerizeSQL = SV_AlertImprovements_Globals::$summerizationAlerts ? 'AND summerize_id is null' : '';
        // *********************
        return $this->_getDb()->fetchOne('
            SELECT COUNT(*)
            FROM xf_user_alert
            WHERE alerted_user_id = ? '.$summerizeSQL.'
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

    protected function _getAlertsFromSource($userId, $fetchMode, array $fetchOptions = array())
    {
        $summarizeThreshold = 4; // $viewingUser['summarizeAlertThreshold']
        $originalLimit = 0;
        $this->standardizeViewingUserReference($viewingUser);
        $summarize = false;
        // determine is summarize needs to occur
        if (($fetchMode == static::FETCH_MODE_POPUP || $fetchMode == static::FETCH_MODE_RECENT) &&
            $viewingUser['alerts_unread'] > 25 &&
            (!isset($fetchOptions['page']) || $fetchOptions['page'] == 0))
        {
            $fetchMode = static::FETCH_MODE_RECENT;
            $summarize = true;
            $fetchOptions['page'] = 0;
            $originalLimit = 25;
            unset($fetchOptions['perPage']);
        }

        // need to replace the entire query...
        $summerizeSQL = SV_AlertImprovements_Globals::$summerizationAlerts ? 'AND summerize_id is null' : '';

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
                WHERE alert.alerted_user_id = ? '.$summerizeSQL.'
                    AND (alert.view_date = 0 OR alert.view_date > ?)
                ORDER BY event_date DESC
            ', $limitOptions['limit'], $limitOptions['offset']
        ), 'alert_id', array($userId, $this->_getFetchModeDateCut($fetchMode)));
        // *********************

        if ($summarize)
        {
            $oldAlerts = $alerts;
            $ungroupedAlerts = array();
            $groupedAlerts = array();
            $db = $this->_getDb();
            // build the list of handlers at once, and exclude based
            $handlers = $this->getAlertHandlers();
            foreach ($handlers AS $key => $handler)
            {
                if (!is_callable(array($handler, 'canSummarize')) ||
                    !is_callable(array($handler, 'summarizeAlerts')))
                {
                    unset($handlers['$key']);
                }
            }

            // collect alerts into groupings by content/id
            $groupedContentAlerts = array();
            $groupedUserAlerts = array();
            foreach ($alerts AS $id => $item)
            {
                if ($item['view_date'] ||
                    empty($handlers[$item['content_type']]) ||
                    $this->endswith($item['action'], '_summary'))
                {
                    $ungroupedAlerts[$id] = $item;
                    continue;
                }
                $handler = $handlers[$item['content_type']];
                if (!$handler->canSummarize($item))
                {
                    $ungroupedAlerts[$id] = $item;
                    continue;
                }
                $groupedContentAlerts[$item['content_type']][$item['content_id']][] = $item;
                //$groupedUserAlerts[$item['user_id']][$item['content_type']][$item['content_id']][] = $item;
            }

            // determine what can be summerised by various types. These require explicit support (ie a template)
            $grouped = 0;
            foreach ($groupedContentAlerts AS $contentType => &$contentIds)
            {
                $handler = $this->_getAlertHandlerFromCache($contentType);
                foreach ($contentIds AS $contentId => $alertGrouping)
                {
                    if ($summarizeThreshold && count($alertGrouping) > $summarizeThreshold)
                    {
                        $lastAlert = end($alertGrouping);
                        // inject a grouped alert with the same content type/id, but with a different action
                        $summaryAlert = array(
                            'alerted_user_id' => $lastAlert['alerted_user_id'],
                            'user_id' => 0,
                            'username' => 'Guest',
                            'content_type' => $contentType,
                            'content_id' => $contentId,
                            'action' => $lastAlert['action'].'_summary',
                            'event_date' => $lastAlert['event_date'],
                            'view_date'  => 0,
                            'extra_data' => array(),
                        );
                        $summaryAlert = $handler->summarizeAlerts($summaryAlert, $alertGrouping);
                        // database update
                        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Alert');
                        $dw->bulkSet($summaryAlert);
                        $dw->save();
                        $summaryAlert = $dw->getMergedData();
                        // bits required for alert processing
                        $summaryAlert['gender'] = null;
                        $summaryAlert['avatar_date'] = null;
                        $summaryAlert['gravatar'] = null;
                        // hide the non-summary alerts
                        $stmt = $db->query('
                            UPDATE xf_user_alert
                            SET summerize_id = ?, view_date = ?
                            WHERE alert_id in (' . $db->quote(XenForo_Application::arrayColumn($alertGrouping, 'alert_id')). ')
                        ', array($summaryAlert['alert_id'], XenForo_Application::$time));
                        $rowsAffected = $stmt->rowCount();
                        $grouped += $rowsAffected;
                        // add to grouping
                        $groupedAlerts[$summaryAlert['alert_id']] = $summaryAlert;
                    }
                    else
                    {
                        $ungroupedAlerts = array_merge($ungroupedAlerts, $alertGrouping);
                        unset($contentIds[$contentId]);
                    }
                }
            }

            // update alert totals
            if ($groupedAlerts)
            {
                $visitor = XenForo_Visitor::getInstance();
                $visitor['alerts_unread'] = $db->fetchOne('
                    SELECT COUNT(*)
                    FROM xf_user_alert
                    WHERE alerted_user_id = ? AND view_date = 0 '.$summerizeSQL.'
                ', array($userId));
                $db->query("
                    update xf_user
                    set alerts_unread = GREATEST(0, cast(alerts_unread as signed) - ?)
                    where user_id = ?
                ", array($grouped, $userId));
            }

            // merge the grouped & ungrouped alerts back together
            $groupedAlerts = array_merge($ungroupedAlerts, $groupedAlerts);
            uasort($groupedAlerts, function($a, $b) {
                if ($a['event_date'] == $b['event_date'])
                {
                    return ($a['alert_id'] < $b['alert_id']) ? 1 : -1;
                }
                return ($a['event_date'] < $b['event_date']) ? 1 : -1;
            });
            $alerts = $groupedAlerts;

            // sanity check
            if ($originalLimit && count($alerts) > $originalLimit)
            {
                $alerts = array_slice($alerts, 0, $originalLimit, true);
            }
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
                        $summerizeSQL = SV_AlertImprovements_Globals::$summerizationAlerts ? 'AND summerize_id is null' : '';
                        // why the hell are we inside a transaction?
                        XenForo_Error::logException($e, false, 'Unexpected transaction; ');
                        $rowsAffected = 0;
                        $visitor['alerts_unread'] = $db->fetchOne('
                            SELECT COUNT(*)
                            FROM xf_user_alert
                            WHERE alerted_user_id = ? AND view_date = 0 '.$summerizeSQL,
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

    public function markUnread($userId, $alertId)
    {
        $db = $this->_getDb();

        XenForo_Db::beginTransaction($db);

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

        XenForo_Db::commit($db);

        $visitor = XenForo_Visitor::getInstance();
        if ($visitor['user_id'] == $userId)
        {
            $visitor['alerts_unread'] += 1;
        }
    }
}