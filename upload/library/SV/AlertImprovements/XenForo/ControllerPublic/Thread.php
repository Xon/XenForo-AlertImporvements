<?php

class SV_AlertImprovements_XenForo_ControllerPublic_Thread extends XFCP_SV_AlertImprovements_XenForo_ControllerPublic_Thread
{
    public function actionIndex()
    {
        $response = parent::actionIndex();
        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['thread']))
        {
            $visitor = XenForo_Visitor::getInstance();
            $userId = $visitor->getUserId();
            if ($userId && $visitor->alerts_unread)
            {

                $threadId = $response->params['thread']['thread_id'];
                $posts = $response->params['posts'];
                $lastPost = end($posts); 
                $firstPost = reset($posts);

                $db = XenForo_Application::getDb();
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
        }
        return $response;
    }
}