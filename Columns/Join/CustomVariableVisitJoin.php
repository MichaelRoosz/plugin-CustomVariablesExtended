<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link http://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomVariablesExtended\Columns\Join;

use Piwik\Columns\Join;

class CustomVariableVisitJoin extends Join {
    public function __construct() {
        parent::__construct('log_custom_variable_visit', 'idvisit', 'value');
    }
}
