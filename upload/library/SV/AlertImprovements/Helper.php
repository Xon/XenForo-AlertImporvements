<?php

abstract class SV_AlertImprovements_Helper
{
    public static function getSummaryAlertLikeRatings(array $summaryAlert, array $alerts)
    {
        if (SV_Utils_AddOn::addOnIsActive('PostRating'))
        {
            $likesByContentType['post'] = 0;
            $min = PHP_INT_MAX;
            $max = -1;
            foreach($alerts as $alert)
            {
                $contentType = $alert['content_type'];
                if ($alert['action'] == 'like')
                {
                    if (!isset($likesByContentType[$contentType]))
                    {
                        $likesByContentType[$contentType] = 0;
                    }
                    $likesByContentType[$contentType] += 1;
                }
                else if ($contentType == 'postrating' && $alert['action'] == 'rate')
                {
                    $ratingIds[$alert['content_id']] = true;
                    if ($alert['event_date'] < $min)
                    {
                        $min = $alert['event_date'];
                    }
                    if ($alert['event_date'] > $max)
                    {
                        $max = $alert['event_date'];
                    }
                }
            }

            if ($max > 0)
            {
                $ratingIds = array_keys($ratingIds);
                $db = XenForo_Application::getDb();
                $ratings = $db->fetchAll('
                    SELECT rating, count(rating) as count
                    FROM dark_postrating
                    where post_id in ('.$db->quote($ratingIds).') and `date` >= ? and `date` <= ?
                    group by rating
                ', array($min, $max));
                $keyedRatings = array();
                foreach($ratings as $rating)
                {
                    $keyedRatings[$rating['rating']] = $rating['count'];
                }
                $likeRatingId = XenForo_Application::GetOptions()->dark_postrating_like_id;
                if ($likeRatingId && !empty($likesByContentType['post']))
                {
                    $keyedRatings[$likeRatingId] = $likesByContentType['post'];
                }
                $summaryAlert['extra_data']['ratings'] = $keyedRatings;
                $summaryAlert['action'] = 'rate_summary';
            }
            if (empty($likesByContentType['post']))
            {
                unset($likesByContentType['post']);
            }
            $summaryAlert['extra_data']['likes'] = $likesByContentType;
        }
        else
        {
            $summaryAlert['extra_data']['likes']['post'] = count($alerts);

        }
        return $summaryAlert;
    }

    public static function prepareRateSummary(array $item, array $viewingUser)
    {
        if (SV_Utils_AddOn::addOnIsActive('PostRating') && !empty($item['extra']['ratings']))
        {
            /** @var Dark_PostRating_Model */
            $ratingModel = XenForo_Model::create('Dark_PostRating_Model');
            $ratings = $ratingModel->getRatings();

            if (empty($ratings))
            {
                unset($item['extra']['ratings']);
            }
            else if (isset($item['extra']['ratings']))
            {
                $item['extra']["totalRatings"] = 0;
                $sortedRatings = array();
                foreach($ratings as $id => $rating)
                {
                    if (isset($item['extra']['ratings'][$id]))
                    {
                        $rating['count'] = $item['extra']['ratings'][$id];
                        if ($rating['count'])
                        {
                            $item['extra']["totalRatings"] += $rating['count'];
                            $sortedRatings[$id] = $rating;
                        }
                    }
                }
                $item['extra']['ratings'] = $sortedRatings;
                unset($item['extra']['likes']['post']);
            }
        }
        if (isset($item['extra']['likes']))
        {
            //x_of_posts
            //x_of_report_comments
            //x_of_conversation_messages
            $item['extra']['likesPhrase'] = array();
            foreach($item['extra']['likes'] as $contentType => $count)
            {
                if ($count)
                {
                    $item['extra']['likesPhrase'][$contentType] = new XenForo_Phrase("x_of_{$contentType}s", array('count' => $count));
                }
            }
        }
        return $item;
    }
}
