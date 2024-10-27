<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link http://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomVariablesExtended;

use Piwik\Piwik;

class Controller extends \Piwik\Plugin\Controller {
    public function manage(): string {
        $this->checkSitePermission();
        Piwik::checkUserHasAdminAccess($this->idSite);

        return $this->renderTemplate('manage', []);
    }

}
