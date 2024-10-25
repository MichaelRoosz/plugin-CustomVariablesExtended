<?php

namespace Piwik\Plugins\CustomVariablesExtended;

use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\View;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableVisit;

class VisitorDetails extends VisitorDetailsAbstract
{
    protected $customVariables = [];

    public function extendVisitorDetails(&$visitor)
    {
        $customVariables = [];

        $visitLogTable = new LogTableVisit();
        $cvars = $visitLogTable->getCustomVariablesForVisit($this->details['idvisit']);

        for ($i = CustomVariablesExtended::FIRST_CUSTOM_VARIABLE_INDEX; $i <= CustomVariablesExtended::LAST_CUSTOM_VARIABLE_INDEX; $i++) {
            $key = 'custom_var_k' . $i;
            if (!empty($cvars[$key])) {
                $customVariables[$i] = array(
                    'customVariableName' . $i  => $cvars[$key]['name'],
                    'customVariableValue' . $i => $cvars[$key]['value'],
                );
            }
        }

        $visitor['customVariablesExtended'] = $customVariables;
    }

    public function extendActionDetails(&$action, $nextAction, $visitorDetails)
    {
        $customVariablesPage = [];

        for ($i = CustomVariablesExtended::FIRST_CUSTOM_VARIABLE_INDEX; $i <= CustomVariablesExtended::LAST_CUSTOM_VARIABLE_INDEX; $i++) {
            if (!empty($action['custom_var_k' . $i])) {
                $cvarKey = $action['custom_var_k' . $i];

                if (in_array($cvarKey, ['_pk_scat', '_pk_scount'])) {
                    continue; // ignore old site search variables
                }

                $customVariablesPage[$i] = array(
                    'customVariablePageName' . $i  => $cvarKey,
                    'customVariablePageValue' . $i => $action['custom_var_v' . $i],
                );
            }
            unset($action['custom_var_k' . $i]);
            unset($action['custom_var_v' . $i]);
        }

        if ($customVariablesPage) {
            $action['customVariablesExtended'] = $customVariablesPage;
        }
    }

    public function renderActionTooltip($action, $visitInfo)
    {
        if (empty($action['customVariablesExtended'])) {
            return [];
        }

        $view = new View('@CustomVariablesExtended/_actionTooltip');
        $view->action = $action;
        return [[ 41, $view->render() ]];
    }

    public function renderVisitorDetails($visitInfo)
    {
        if (empty($visitInfo['customVariablesExtended'])) {
            return [];
        }

        $view = new View('@CustomVariablesExtended/_visitorDetails');
        $view->visitInfo = $visitInfo;
        return [[ 51, $view->render() ]];
    }

    public function initProfile($visits, &$profile)
    {
        $this->customVariables = [
            CustomVariablesExtended::SCOPE_PAGE => [],
            CustomVariablesExtended::SCOPE_VISIT  => [],
        ];
    }

    public function handleProfileAction($action, &$profile)
    {
        if (empty($action['customVariablesExtended'])) {
            return;
        }

        foreach ($action['customVariablesExtended'] as $index => $customVariable) {

            $scope = CustomVariablesExtended::SCOPE_PAGE;
            $name = $customVariable['customVariablePageName'.$index];
            $value = $customVariable['customVariablePageValue'.$index];

            if (!$value) {
                continue;
            }

            if (!array_key_exists($name, $this->customVariables[$scope])) {
                $this->customVariables[$scope][$name] = [];
            }

            if (!array_key_exists($value, $this->customVariables[$scope][$name])) {
                $this->customVariables[$scope][$name][$value] = 0;
            }

            $this->customVariables[$scope][$name][$value]++;
        }
    }

    public function handleProfileVisit($visit, &$profile)
    {
        if (empty($visit['customVariablesExtended'])) {
            return;
        }

        foreach ($visit['customVariablesExtended'] as $index => $customVariable) {

            $scope = CustomVariablesExtended::SCOPE_VISIT;
            $name = $customVariable['customVariableName'.$index];
            $value = $customVariable['customVariableValue'.$index];

            if (!$value) {
                continue;
            }

            if (!array_key_exists($name, $this->customVariables[$scope])) {
                $this->customVariables[$scope][$name] = [];
            }

            if (!array_key_exists($value, $this->customVariables[$scope][$name])) {
                $this->customVariables[$scope][$name][$value] = 0;
            }

            $this->customVariables[$scope][$name][$value]++;
        }
    }

    public function finalizeProfile($visits, &$profile)
    {
        $customVariables = $this->customVariables;
        foreach ($customVariables as $scope => &$variables) {

            if (!$variables) {
                unset($customVariables[$scope]);
                continue;
            }

            foreach ($variables as $name => &$values) {
                arsort($values);
            }
        }
        if ($customVariables) {
            $profile['customVariablesExtended'] = $this->convertForProfile($customVariables);
        }
    }

    protected function convertForProfile($customVariables)
    {
        $convertedVariables = [];

        foreach ($customVariables as $scope => $scopeVariables) {

            $convertedVariables[$scope] = [];

            foreach ($scopeVariables as $name => $values) {

                $variable = [
                    'name' => $name,
                    'values' => []
                ];

                foreach ($values as $value => $count) {
                    $variable['values'][] = [
                        'value' => $value,
                        'count' => $count
                    ];
                }

                $convertedVariables[$scope][] = $variable;
            }
        }

        return $convertedVariables;
    }
}
