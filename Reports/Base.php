<?php

namespace Piwik\Plugins\CustomVariablesExtended\Reports;

abstract class Base extends \Piwik\Plugin\Report
{
    protected $defaultSortColumn = 'nb_actions';

    protected function init(): void
    {
        $this->categoryId = 'General_Visitors';
        $this->onlineGuideUrl = 'https://matomo.org/docs/custom-variables/';
    }

}
