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
        if (empty($contentIds))
        {
            return;
        }

        $visitor = XenForo_Visitor::getInstance();
        $userId = $visitor->getUserId();

        $db = $this->_getDb();
        $stmt = $db->query("
            update ignore xf_user_alert
            set view_date = ?
            where alerted_user_id = ? and view_date = 0 and content_type in(". $db->quote($contentType) .") and content_id in (". $db->quote($contentIds) .")
        ", array(XenForo_Application::$time, $userId));
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
                $visitor['alerts_unread'] -= $rowsAffected;
                if ($visitor['alerts_unread'] < 0)
                {
                    $visitor['alerts_unread'] = 0;
                }
            }
            catch(Zend_Db_Statement_Mysqli_Exception $e)
            {
                // something went wrong, recount the alerts and return
                if (stripos($e->getMessage(), "Deadlock found when trying to get lock; try restarting transaction") !== false)
                {
                    $visitor['alerts_unread'] = $db->fetchOne('
                        SELECT COUNT(*)
                        FROM xf_user_alert
                        WHERE alerted_user_id = ? AND view_date = 0',
                    array($userId, $this->_getFetchModeDateCut(self::FETCH_MODE_RECENT)));
                }
                else
                {
                    throw $e;
                }
            }
        }
    }

    public function markUnread($userId, $alertId)
    {
        $db = $this->_getDb();

        XenForo_Db::beginTransaction($db);

        $db->query("
            update xf_user
            set alerts_unread = alerts_unread + 1
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