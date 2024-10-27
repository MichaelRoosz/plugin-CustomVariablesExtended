<?php

namespace Piwik\Plugins\CustomVariablesExtended;

use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\View;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableVisit;

class VisitorDetails extends VisitorDetailsAbstract
{
    /**
     * @var array<string,array<string,array<string,int>>> $customVariables
     */
    protected $customVariables = [];

    /**
     * @param array<string,mixed> $visitor
     */
    public function extendVisitorDetails(&$visitor): void
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

    /**
     * @param array<string,mixed> $action
     * @param array<string,mixed> $nextAction
     * @param array<string,mixed> $visitorDetails
     */
    public function extendActionDetails(&$action, $nextAction, $visitorDetails): void
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

    /**
     * @param array<string,mixed> $action
     * @param array<string,mixed> $visitInfo
     *
     * @return array<array<int|string>>
     */
    public function renderActionTooltip($action, $visitInfo): array
    {
        if (empty($action['customVariablesExtended'])) {
            return [];
        }

        $view = new View('@CustomVariablesExtended/_actionTooltip');

        /** @phpstan-ignore property.notFound */
        $view->action = $action;

        return [[ 41, $view->render() ]];
    }

    /**
     * @param array<string,mixed> $visitInfo
     *
     * @return array<array<int|string>>
     */
    public function renderVisitorDetails($visitInfo): array
    {
        if (empty($visitInfo['customVariablesExtended'])) {
            return [];
        }

        $view = new View('@CustomVariablesExtended/_visitorDetails');

        /** @phpstan-ignore property.notFound */
        $view->visitInfo = $visitInfo;

        return [[ 51, $view->render() ]];
    }

    /**
     * @param \Piwik\DataTable $visits
     * @param array<string,mixed> $profile
     */
    public function initProfile($visits, &$profile): void
    {
        $this->customVariables = [
            CustomVariablesExtended::SCOPE_PAGE => [],
            CustomVariablesExtended::SCOPE_VISIT  => [],
        ];
    }

    /**
     * @param array<string,mixed> $action
     * @param array<string,mixed> $profile
     */
    public function handleProfileAction($action, &$profile): void
    {
        if (!is_array($action)) {
            return;
        }

        if (empty($action['customVariablesExtended'])
            || !is_array($action['customVariablesExtended'])) {
            return;
        }

        foreach ($action['customVariablesExtended'] as $index => $customVariable) {

            $scope = CustomVariablesExtended::SCOPE_PAGE;
            $name = (string) $customVariable['customVariablePageName'.$index];
            $value = (string) $customVariable['customVariablePageValue'.$index];

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

    /**
     * @param \Piwik\DataTable\Row $visit
     * @param array<string,mixed> $profile
     */
    public function handleProfileVisit($visit, &$profile): void
    {
        if (empty($visit['customVariablesExtended'])
            || !is_array($visit['customVariablesExtended'])) {
            return;
        }

        foreach ($visit['customVariablesExtended'] as $index => $customVariable) {

            $scope = CustomVariablesExtended::SCOPE_VISIT;
            $name = (string) $customVariable['customVariableName'.$index];
            $value = (string) $customVariable['customVariableValue'.$index];

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

    /**
     * @param \Piwik\DataTable $visits
     * @param array<string,mixed> $profile
     */
    public function finalizeProfile($visits, &$profile): void
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

    /**
     * @param array<string,array<string,array<string,int>>> $customVariables
     *
     * @return array<string,array<array{
     *   name: string,
     *   values: array<array{
     *      value: string,
     *      count: int
     *   }>
     * }>>
     */
    protected function convertForProfile(array $customVariables): array
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
