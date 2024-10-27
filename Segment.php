<?php

namespace Piwik\Plugins\CustomVariablesExtended;

class Segment extends \Piwik\Plugin\Segment {
    protected function init(): void {
        $this->setCategory('CustomVariablesExtended_CustomVariables');
    }
}
