<?php

namespace Piwik\Plugins\CustomVariablesExtended\Columns\Join;

use Piwik\Columns\Join;

class CustomVariableLinkVisitActionJoin extends Join
{
    public function __construct()
    {
        parent::__construct('log_custom_variable_link_va', 'idlink_va', 'value');
    }
}
