<?php

namespace Piwik\Plugins\CustomVariablesExtended\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CustomVariablesExtended\CustomVariableDimension;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class GetCustomVariablesValuesFromNameId extends Base {
    public function configureView(ViewDataTable $view): void {
        $view->config->columns_to_display = ['label', 'nb_actions', 'nb_visits'];
        $view->config->show_goals  = true;
        $view->config->show_search = false;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('CustomVariablesExtended_ColumnCustomVariableValue'));
        $view->requestConfig->filter_sort_column = 'nb_actions';
        $view->requestConfig->filter_sort_order  = 'desc';
    }
    protected function init(): void {
        $dimension = new CustomVariableDimension();
        $dimension->initCustomDimension(CustomVariablesExtended::SCOPE_VISIT, CustomVariablesExtended::FIRST_CUSTOM_VARIABLE_INDEX);

        parent::init();
        $this->dimension     = $dimension;
        $this->name          = Piwik::translate('CustomVariablesExtended_CustomVariables');
        $this->documentation = Piwik::translate(
            'CustomVariablesExtended_CustomVariablesReportDocumentation',
            ['<br />', '<a href="https://matomo.org/docs/custom-variables/" rel="noreferrer noopener" target="_blank">', '</a>']
        );
        $this->isSubtableReport = true;
        $this->order = 15;
    }

}
