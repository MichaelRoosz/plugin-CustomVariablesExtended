<?php

namespace Piwik\Plugins\CustomVariablesExtended\Reports;

use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CustomVariablesExtended\CustomVariableDimension;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class GetCustomVariables extends Base
{
    protected function init(): void
    {
        $dimension = new CustomVariableDimension();
        $dimension->initCustomDimension(CustomVariablesExtended::SCOPE_VISIT, CustomVariablesExtended::FIRST_CUSTOM_VARIABLE_INDEX);

        parent::init();
        $this->dimension     = $dimension;
        $this->name          = Piwik::translate('CustomVariablesExtended_CustomVariables');
        $this->documentation = Piwik::translate(
            'CustomVariablesExtended_CustomVariablesReportDocumentation',
            array('<br />', '<a href="https://matomo.org/docs/custom-variables/" rel="noreferrer noopener" target="_blank">', '</a>')
        );
        $this->actionToLoadSubTables = 'getCustomVariablesValuesFromNameId';
        $this->order = 10;

        $this->subcategoryId    = 'CustomVariablesExtended_CustomVariables';
        $this->hasGoalMetrics = false;
    }

    public function prepareForGoalMetrics(): void
    {
        $this->hasGoalMetrics = true;
        $this->categoryId = 'VisitsSummary_VisitsSummary';
    }

    public function configureView(ViewDataTable $view): void
    {
        $view->config->columns_to_display = array('label', 'nb_actions', 'nb_visits');
        $view->config->addTranslation('label', Piwik::translate('CustomVariablesExtended_ColumnCustomVariableName'));
        $view->requestConfig->filter_sort_column = 'nb_actions';
        $view->requestConfig->filter_sort_order  = 'desc';

        $that = $this;
        $view->config->filters[] = function (DataTable $table) use ($view, $that) {
            if ($that->isReportContainsUnsetVisitsColumns($table)) {
                $message = $that->getFooterMessageExplanationMissingMetrics();
                $view->config->show_footer_message = $message;
            }
        };
    }

    public function getFooterMessageExplanationMissingMetrics(): string
    {
        $metrics = sprintf(
            "'%s', '%s' %s '%s'",
            Piwik::translate('General_ColumnNbVisits'),
            Piwik::translate('General_ColumnNbUniqVisitors'),
            Piwik::translate('General_And'),
            Piwik::translate('General_ColumnNbUsers')
        );
        $messageStart = Piwik::translate('CustomVariablesExtended_MetricsAreOnlyAvailableForVisitScope', array($metrics, "'visit'"));

        $messageEnd = Piwik::translate('CustomVariablesExtended_MetricsNotAvailableForPageScope', array("'page'", '\'-\''));

        $message = $messageStart . ' ' . $messageEnd;

        if (!$this->isSubtableReport) {
            // no footer message for subtables
            $out = '';
            Piwik::postEvent('Template.afterCustomVariablesReport', array(&$out));
            $message .= $out;
        }

        return $message;
    }

    public function isReportContainsUnsetVisitsColumns(DataTable $report): bool
    {
        $visits = $report->getColumn('nb_visits');
        $isVisitsMetricsSometimesUnset = in_array(false, $visits);
        return $isVisitsMetricsSometimesUnset;
    }
}
