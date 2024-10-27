<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link http://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomVariablesExtended\ProfileSummary;

use Piwik\Piwik;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;
use Piwik\Plugins\Live\ProfileSummary\ProfileSummaryAbstract;
use Piwik\View;

class VisitScopeSummary extends ProfileSummaryAbstract {
    /**
     * @inheritdoc
     */
    public function getName() {
        return Piwik::translate('CustomVariablesExtended_CustomVariables') . ' ' . Piwik::translate('General_TrackingScopeVisit');
    }

    /**
     * @inheritdoc
     */
    public function render() {
        if (empty($this->profile['customVariablesExtended']) || empty($this->profile['customVariablesExtended'][CustomVariablesExtended::SCOPE_VISIT])) {
            return '';
        }

        $view = new View('@CustomVariablesExtended/_profileSummary.twig');

        /** @phpstan-ignore property.notFound */
        $view->visitorData = $this->profile;

        /** @phpstan-ignore property.notFound */
        $view->scopeName   = Piwik::translate('General_TrackingScopeVisit');

        /** @phpstan-ignore property.notFound */
        $view->variables   = $this->profile['customVariablesExtended'][CustomVariablesExtended::SCOPE_VISIT];

        return $view->render();
    }

    /**
     * @inheritdoc
     */
    public function getOrder() {
        return 16;
    }
}
