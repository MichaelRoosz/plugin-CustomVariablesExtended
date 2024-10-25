<?php

namespace Piwik\Plugins\CustomVariablesExtended\ProfileSummary;

use Piwik\Piwik;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;
use Piwik\Plugins\Live\ProfileSummary\ProfileSummaryAbstract;
use Piwik\View;

/**
 * Class VisitScopeSummary
 */
class VisitScopeSummary extends ProfileSummaryAbstract
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return Piwik::translate('CustomVariablesExtended_CustomVariables') . ' ' . Piwik::translate('General_TrackingScopeVisit');
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        if (empty($this->profile['customVariablesExtended']) || empty($this->profile['customVariablesExtended'][CustomVariablesExtended::SCOPE_VISIT])) {
            return '';
        }

        $view              = new View('@CustomVariablesExtended/_profileSummary.twig');
        $view->visitorData = $this->profile;
        $view->scopeName   = Piwik::translate('General_TrackingScopeVisit');
        $view->variables   = $this->profile['customVariablesExtended'][CustomVariablesExtended::SCOPE_VISIT];
        return $view->render();
    }

    /**
     * @inheritdoc
     */
    public function getOrder()
    {
        return 16;
    }
}
