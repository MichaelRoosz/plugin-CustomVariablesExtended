<?php

namespace Piwik\Plugins\CustomVariablesExtended;

use Exception;
use Piwik\Common;
use Piwik\Plugin;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableConversion;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableLinkVisitAction;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableVisit;
use Piwik\Plugins\CustomVariablesExtended\Reports\GetCustomVariables;
use Piwik\Plugins\CustomVariablesExtended\Tracker\CustomVariablesExtendedRequestProcessor;

class CustomVariablesExtended extends Plugin
{
    public const MAX_LENGTH_VARIABLE_NAME = 200;
    public const MAX_LENGTH_VARIABLE_VALUE = 1024;

    public const FIRST_CUSTOM_VARIABLE_INDEX = 101;
    public const LAST_CUSTOM_VARIABLE_INDEX = 120;

    public const SCOPE_PAGE = 'page';
    public const SCOPE_VISIT = 'visit';
    public const SCOPE_CONVERSION = 'conversion';

    public const SCOPE_ID_PAGE = 1;
    public const SCOPE_ID_VISIT = 2;
    public const SCOPE_ID_CONVERSION = 3;

    public function isTrackerPlugin(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function registerEvents(): array
    {
        return [
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'AssetManager.getStylesheetFiles'  => 'getStylesheetFiles',
            'Dimension.addDimensions' => 'addDimensions',
            'Actions.getCustomActionDimensionFieldsAndJoins' => 'provideActionDimensionFields',
            'Tracker.newConversionInformation' => 'newConversionInformation',
            'Goals.getReportsWithGoalMetrics'  => 'getReportsWithGoalMetrics',
            'Db.getTablesInstalled' => 'getTablesInstalled',
        ];
    }

    public function install(): void
    {
        (new LogTableConversion())->install();
        (new LogTableLinkVisitAction())->install();
        (new LogTableVisit())->install();
    }

    public function uninstall(): void
    {
        (new LogTableConversion())->uninstall();
        (new LogTableLinkVisitAction())->uninstall();
        (new LogTableVisit())->uninstall();
    }

    /**
     * @param array<string> $translationKeys
     */
    public function getClientSideTranslationKeys(array &$translationKeys): void
    {
        $translationKeys[] = 'CustomVariablesExtended_CustomVariables';
        $translationKeys[] = 'CustomVariablesExtended_ManageDescription';
        $translationKeys[] = 'CustomVariablesExtended_ScopeX';
        $translationKeys[] = 'CustomVariablesExtended_Index';
        $translationKeys[] = 'CustomVariablesExtended_Usages';
        $translationKeys[] = 'CustomVariablesExtended_Unused';
        $translationKeys[] = 'CustomVariablesExtended_CreateNewSlot';
        $translationKeys[] = 'CustomVariablesExtended_UsageDetails';
        $translationKeys[] = 'CustomVariablesExtended_CurrentAvailableCustomVariables';
        $translationKeys[] = 'CustomVariablesExtended_ToCreateCustomVarExecute';
        $translationKeys[] = 'CustomVariablesExtended_CreatingCustomVariableTakesTime';
        $translationKeys[] = 'CustomVariablesExtended_SlotsReportIsGeneratedOverTime';
        $translationKeys[] = 'General_Loading';
        $translationKeys[] = 'General_TrackingScopeVisit';
        $translationKeys[] = 'General_TrackingScopePage';
    }

    /**
     * @param array<string> $stylesheets
     */
    public function getStylesheetFiles(&$stylesheets): void
    {
        $stylesheets[] = "plugins/CustomVariablesExtended/vue/src/ManageCustomVars/ManageCustomVars.less";
    }

    /**
     * @param array<\Piwik\Columns\Dimension> $dimensions
     */
    public function addDimensions(&$dimensions): void
    {
        foreach ([self::SCOPE_VISIT, self::SCOPE_PAGE, self::SCOPE_CONVERSION] as $scope) {
            for ($i = self::FIRST_CUSTOM_VARIABLE_INDEX; $i <= self::LAST_CUSTOM_VARIABLE_INDEX; $i++) {
                $custom = new CustomVariableDimension();
                $custom->initCustomDimension($scope, $i);
                $dimensions[] = $custom;
            }
        }
    }

    /**
     * @param array<string> $fields
     * @param array<string> $joins
     */
    public function provideActionDimensionFields(&$fields, &$joins): void
    {
        for ($i = self::FIRST_CUSTOM_VARIABLE_INDEX; $i <= self::LAST_CUSTOM_VARIABLE_INDEX; $i++) {
            $fields[] = 'cv_lva_'. $i . '.name as custom_var_k' . $i;
            $fields[] = 'cv_lva_'. $i . '.value as custom_var_v' . $i;

            $joins[] = 'LEFT JOIN ' . Common::prefixTable('log_custom_variable_link_va')
                    . ' AS cv_lva_'. $i
                    . ' ON log_link_visit_action.idvisit = cv_lva_'. $i . '.idvisit'
                    . ' AND log_link_visit_action.idlink_va = cv_lva_'. $i . '.idlink_va'
                    . ' AND cv_lva_'. $i . '.index = ' . $i;
        }
    }

    /**
     * @param array{idgoal: int, buster: int} $conversion
     * @param array{idvisit: int} $visitInformation
     * @param \Piwik\Tracker\Request $request
     * @param \Piwik\Tracker\Action|null $action
     */
    public function newConversionInformation(&$conversion, $visitInformation, $request, $action): void
    {
        $processor = new CustomVariablesExtendedRequestProcessor();
        $processor->onNewConversionInformation($conversion, $visitInformation, $request, $action);
    }

    /**
     * @param array<string, mixed> $reportsWithGoals
     */
    public function getReportsWithGoalMetrics(&$reportsWithGoals): void
    {
        $report = new GetCustomVariables();
        $report->prepareForGoalMetrics();

        $reportToInsert = [
            'category' => $report->getCategoryId(),
            'name'     => $report->getName(),
            'module'   => $report->getModule(),
            'action'   => $report->getAction(),
            'parameters' => $report->getParameters()
        ];

        $newReportsWithGoals = [];
        $didInsert = false;

        foreach ($reportsWithGoals as $reportWithGoals) {
            $newReportsWithGoals[] = $reportWithGoals;

            if (is_array($reportWithGoals)
                && $reportWithGoals['module'] === 'CustomVariables') {

                $newReportsWithGoals[] = $reportToInsert;
                $didInsert = true;
            }
        }

        if (!$didInsert) {
            $newReportsWithGoals[] = $reportToInsert;
        }

        $reportsWithGoals = $newReportsWithGoals;
    }

    /**
     * @param array<string> $allTablesInstalled
     */
    public function getTablesInstalled(&$allTablesInstalled): void
    {
        $allTablesInstalled[] = Common::prefixTable(LogTableVisit::TABLE_NAME);
        $allTablesInstalled[] = Common::prefixTable(LogTableLinkVisitAction::TABLE_NAME);
        $allTablesInstalled[] = Common::prefixTable(LogTableConversion::TABLE_NAME);
    }

    public static function scopeNameToId(string $scope): int
    {
        switch ($scope) {
            case self::SCOPE_VISIT:
                return self::SCOPE_ID_VISIT;
            case self::SCOPE_PAGE:
                return self::SCOPE_ID_PAGE;
            case self::SCOPE_CONVERSION:
                return self::SCOPE_ID_CONVERSION;
        }

        throw new Exception('Invalid scope');
    }

    public static function scopeIdToName(int $scopeId): string
    {
        switch ($scopeId) {
            case self::SCOPE_ID_VISIT:
                return self::SCOPE_VISIT;
            case self::SCOPE_ID_PAGE:
                return self::SCOPE_PAGE;
            case self::SCOPE_ID_CONVERSION:
                return self::SCOPE_CONVERSION;
        }

        throw new Exception('Invalid scope');
    }
}
