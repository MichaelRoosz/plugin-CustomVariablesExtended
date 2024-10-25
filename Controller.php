<?php

namespace Piwik\Plugins\CustomVariablesExtended;

use Piwik\Piwik;

class Controller extends \Piwik\Plugin\Controller
{
    public function manage()
    {
        $this->checkSitePermission();
        Piwik::checkUserHasAdminAccess($this->idSite);

        return $this->renderTemplate('manage', array());
    }

}