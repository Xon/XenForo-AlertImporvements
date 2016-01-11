<?php

class SV_AlertImprovements_XenForo_Model_Alert extends XFCP_SV_AlertImprovements_XenForo_Model_Alert
{
    public function markAllAlertsReadForUser($userId, $time = null)
    {
        SV_AlertImprovements_Globals::$markedAlertsRead = true;
        parent::markAllAlertsReadForUser($userId, $time);
    }

    public function markPostsAsRead($threadId, array $posts)
    {
        if (empty($posts))
        {
            return;
        }

        $visitor = XenForo_Visitor::getInstance();
        $userId = $visitor->getUserId();

        $postIds = XenForo_Application::arrayColumn($posts, 'post_id');

        $db = $this->_getDb();
        $stmt = $db->query("
            update ignore xf_user_alert
            set view_date = ?
            where alerted_user_id = ? and view_date = 0 and content_type = 'post' and content_id in (". $db->quote($postIds) .")
        ", array(XenForo_Application::$time, $userId));
        $rowsAffected = $stmt->rowCount();

        if ($rowsAffected)
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