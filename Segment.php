<?php

namespace Piwik\Plugins\CustomVariablesExtended;

class Segment extends \Piwik\Plugin\Segment
{
    protected function init()
    {
        $this->setCategory('CustomVariablesExtended_CustomVariables');
    }
}
