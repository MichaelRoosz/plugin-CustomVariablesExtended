<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link http://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomVariablesExtended;

class Segment extends \Piwik\Plugin\Segment {
    protected function init(): void {
        $this->setCategory('CustomVariablesExtended_CustomVariables');
    }
}
