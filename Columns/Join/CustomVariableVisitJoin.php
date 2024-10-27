<?php

namespace Piwik\Plugins\CustomVariablesExtended\Columns\Join;

use Piwik\Columns\Join;

class CustomVariableVisitJoin extends Join {
    public function __construct() {
        parent::__construct('log_custom_variable_visit', 'idvisit', 'value');
    }
}
