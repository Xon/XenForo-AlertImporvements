<?php

class SV_AlertImprovements_Dark_PostRating_AlertHandler extends XFCP_SV_AlertImprovements_Dark_PostRating_AlertHandler
{
    public function canSummarize(array $alert)
    {
        return true;
    }

    protected function _prepareRate_summary(array $item, array $viewingUser)
    {
        if (is_callable('parent::_prepareRate_summary'))
        {
            $item = parent::_prepareRate_summary($item, $viewingUser);
        }

        /** @var Dark_PostRating_Model */
        $ratingModel = XenForo_Model::create('Dark_PostRating_Model');
        $ratings = $ratingModel->getRatings();

        if (empty($ratings))
        {
            unset($item['extra']['ratings']);
        }
        else if (isset($item['extra']['ratings']))
        {
            foreach($item['extra']['ratings'] as $key => &$rating)
            {
                $id = $rating['rating'];
                if (isset($ratings[$id]))
                {
                    $rating['rating'] = $ratings[$id];
                }
                else
                {
                    unset($item['extra']['ratings'][$key]);
                }
            }
        }

        return $item;
    }

    public function summarizeAlerts(array $summaryAlert, array $alerts, $groupingStyle)
    {
        if ($groupingStyle != 'content')
        {
            return null;
        }
        /*
        'user_id' => 0,
        'username' => 'Guest',
        'content_type' => $contentType,
        'content_id' => $contentId,
        'action' => $lastAlert['action'].'_summary',
        */
        
        $alert = end($alerts);
        $min = $alert['event_date'];
        $alert = reset($alerts);
        $max = $alert['event_date'];

        $db = XenForo_Application::getDb();
        $ratings = $db->fetchAll('
            SELECT rating, count(rating) as count
            FROM dark_postrating
            where post_id = ? and `date` >= ? and `date` <= ?
            group by rating
        ', array($summaryAlert['content_id'], $min, $max));

        $summaryAlert['extra_data']['ratings'] = $ratings;
        return $summaryAlert;
    }
}