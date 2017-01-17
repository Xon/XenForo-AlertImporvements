<?php

class SV_AlertImprovements_XenForo_Model_Alert extends XFCP_SV_AlertImprovements_XenForo_Model_Alert
{
    public function markAllAlertsReadForUser($userId, $time = null)
    {
        SV_AlertImprovements_Globals::$markedAlertsRead = true;
        parent::markAllAlertsReadForUser($userId, $time);
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
        if (true)//$options->sv_filterAlertContentIds)
        {
            $contentIds = $db->fetchCol("
                select content_id
                from xf_user_alert
                where alerted_user_id = ? and view_date = 0 and event_date < ? and content_type in(". $db->quote($contentType) .") and content_id in (". $db->quote($contentIds) .")
            ", array($userId, XenForo_Application::$time));
            if (empty($contentIds))
            {
                return;
            }
        }

        $stmt = $db->query("
            update ignore xf_user_alert
            set view_date = ?
            where alerted_user_id = ? and view_date = 0 and event_date < ? and content_type in(". $db->quote($contentType) .") and content_id in (". $db->quote($contentIds) .")
        ", array(XenForo_Application::$time, $userId, XenForo_Application::$time));
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
                            WHERE alerted_user_id = ? AND view_date = 0',
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