<?php

class SV_AlertImprovements_ViewPublic_Account_SummaryAlerts extends XenForo_ViewPublic_Base
{
    public function renderHtml()
    {
        $alerts = XenForo_ViewPublic_Helper_Alert::getTemplates(
            $this,
            array($this->_params['summaryAlert']),
            $this->_params['alertHandlers']
        );
        $this->_params['summaryAlert'] = reset($alerts);

        $this->_params['alerts'] = XenForo_ViewPublic_Helper_Alert::dateSplit(
            XenForo_ViewPublic_Helper_Alert::getTemplates(
                $this,
                $this->_params['alerts'],
                $this->_params['alertHandlers']
            ), 'event_date'
        );
    }
}