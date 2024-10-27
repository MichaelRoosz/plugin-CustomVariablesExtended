<?php

namespace Piwik\Plugins\CustomVariablesExtended;

use Piwik\Common;
use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\UserPreferences;

class Menu extends \Piwik\Plugin\Menu {
    public function configureAdminMenu(MenuAdmin $menu): void {
        $userPreferences = new UserPreferences();
        $default = (int) $userPreferences->getDefaultWebsiteId();

        /** @phpstan-ignore argument.type */
        $idSite = Common::getRequestVar('idSite', $default, 'int');

        $idSite = is_numeric($idSite) ? (int) $idSite : $default;

        if (Piwik::isUserHasAdminAccess($idSite)) {
            $menu->addDiagnosticItem('CustomVariablesExtended_CustomVariables', $this->urlForAction('manage'), $orderId = 21);
        }
    }
}
