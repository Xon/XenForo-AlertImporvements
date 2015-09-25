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

        $lastPost = end($posts);
        $firstPost = reset($posts);

        $db = $this->_getDb();

        $stmt = $db->query("
            update xf_user_alert AS alert
            join xf_post AS posts on posts.post_id = alert.content_id and alert.content_type = 'post'
            set alert.view_date = ?
            where alert.alerted_user_id = ? and alert.view_date = 0 and posts.thread_id = ? and posts.position >= ? and posts.position <= ?
        ", array(XenForo_Application::$time, $userId, $threadId, $firstPost['position'], $lastPost['position']));
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