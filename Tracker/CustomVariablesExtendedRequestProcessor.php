<?php

namespace Piwik\Plugins\CustomVariablesExtended\Tracker;

use Exception;
use Piwik\Common;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\RequestProcessor;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableConversion;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableLinkVisitAction;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableVisit;

class CustomVariablesExtendedRequestProcessor extends RequestProcessor {
    public function processRequestParams(VisitProperties $visitProperties, Request $request): bool {
        // TODO: re-add optimization where if custom variables exist in request, don't bother selecting them in Visitor
        $visitorCustomVariables = self::getCustomVariablesInVisitScope($request);
        if ($visitorCustomVariables) {
            Common::printDebug('Visit level Custom Variables Extended: ');
            Common::printDebug($visitorCustomVariables);
        }

        $request->setMetadata('CustomVariablesExtended', 'visitCustomVariablesExtended', $visitorCustomVariables);

        return false;
    }

    public function recordLogs(VisitProperties $visitProperties, Request $request): void {
        $idVisit = $visitProperties->getProperty('idvisit');
        if (!is_int($idVisit)) {
            if (is_numeric($idVisit)) {
                $idVisit = (int) $idVisit;
            } else {
                throw new Exception('idvisit is not an integer');
            }
        }

        //
        // scope visit
        //
        $visitCustomVariables = $request->getMetadata('CustomVariablesExtended', 'visitCustomVariablesExtended');

        if (is_array($visitCustomVariables) && $visitCustomVariables) {
            $logTableVisit = new LogTableVisit();

            foreach ($visitCustomVariables as $index => $data) {
                $logTableVisit->insertCustomVariable(
                    $request->getIdSite(),
                    $idVisit,
                    $index,
                    $data['name'],
                    $data['value']
                );
            }
        }

        //
        // scope action
        //
        /** @var Action $action */
        $action = $request->getMetadata('Actions', 'action');

        if (
            $action !== null
            && !$request->getMetadata('CoreHome', 'visitorNotFoundInDb')
        ) {

            if (!$action || !($action instanceof Action)) {
                return;
            }

            $customVariables = self::getCustomVariablesInPageScope($request);

            if ($customVariables) {
                Common::printDebug('Page level Custom Variables Extended: ');
                Common::printDebug($customVariables);

                $logTableLinkVisitAction = new LogTableLinkVisitAction();

                foreach ($customVariables as $index => $data) {
                    $logTableLinkVisitAction->insertCustomVariable(
                        $request->getIdSite(),
                        $idVisit,
                        $action->getIdLinkVisitAction(),
                        $index,
                        $data['name'],
                        $data['value']
                    );
                }
            }
        }
    }

    /**
     * @param array{idgoal: int, buster: int} $conversion
     * @param array{idvisit: int} $visitInformation
     * @param \Piwik\Tracker\Request $request
     * @param \Piwik\Tracker\Action|null $action
     */
    public function onNewConversionInformation(&$conversion, $visitInformation, $request, $action): void {
        $logTableConversion = new LogTableConversion();

        //
        // scope visit
        //
        $visitCustomVariables = $request->getMetadata('CustomVariablesExtended', 'visitCustomVariablesExtended') ?: [];
        if (is_array($visitCustomVariables) &&  $visitCustomVariables) {
            foreach ($visitCustomVariables as $index => $data) {
                $logTableConversion->insertCustomVariable(
                    $request->getIdSite(),
                    $visitInformation['idvisit'],
                    $conversion['idgoal'],
                    $conversion['buster'],
                    CustomVariablesExtended::SCOPE_VISIT,
                    $index,
                    $data['name'],
                    $data['value']
                );
            }
        }

        //
        // scope action
        //
        if (
            $action !== null
            && !$request->getMetadata('CoreHome', 'visitorNotFoundInDb')
        ) {

            if (!$action || !($action instanceof Action)) {
                return;
            }

            $customVariables = self::getCustomVariablesInPageScope($request);

            if ($customVariables) {
                Common::printDebug('Page level Custom Variables Extended (conversion): ');
                Common::printDebug($customVariables);

                foreach ($customVariables as $index => $data) {
                    $logTableConversion->insertCustomVariable(
                        $request->getIdSite(),
                        $visitInformation['idvisit'],
                        $conversion['idgoal'],
                        $conversion['buster'],
                        CustomVariablesExtended::SCOPE_PAGE,
                        $index,
                        $data['name'],
                        $data['value']
                    );
                }
            }
        }
    }

    /**
     * @return array<int, array{
     *   name: string,
     *   value: string
     * }>
     */
    public static function getCustomVariablesInVisitScope(Request $request): array {
        return self::getCustomVariables($request, '_cvar');
    }

    /**
     * @return array<int, array{
     *   name: string,
     *   value: string
     * }>
     */
    public static function getCustomVariablesInPageScope(Request $request): array {
        return self::getCustomVariables($request, 'cvar');
    }

    public static function truncateCustomVariableName(string $input): string {
        return mb_substr(trim($input), 0, CustomVariablesExtended::MAX_LENGTH_VARIABLE_NAME);
    }

    public static function truncateCustomVariableValue(string $input): string {
        return mb_substr(trim($input), 0, CustomVariablesExtended::MAX_LENGTH_VARIABLE_VALUE);
    }

    /**
     * @return array<int, array{
     *   name: string,
     *   value: string
     * }>
     */
    private static function getCustomVariables(Request $request, string $parameter): array {
        $cvar = Common::getRequestVar($parameter, '', 'json', $request->getParams());
        if (!is_array($cvar) && !is_string($cvar)) {
            return [];
        }

        $customVar = Common::unsanitizeInputValues($cvar);
        if (!is_array($customVar)) {
            return [];
        }

        $customVariables = [];

        foreach ($customVar as $index => $keyValue) {
            $index = (int) $index;

            if (!is_array($keyValue)) {
                continue;
            }

            if ($index < CustomVariablesExtended::FIRST_CUSTOM_VARIABLE_INDEX
                || $index > CustomVariablesExtended::LAST_CUSTOM_VARIABLE_INDEX
            ) {
                continue;
            }

            if (count($keyValue) != 2
                || (!is_string($keyValue[0]) && !is_numeric($keyValue[0])
                    || (!is_string($keyValue[1]) && !is_numeric($keyValue[1])))
            ) {
                Common::printDebug("Invalid custom variables detected (index=$index)");

                continue;
            }

            $keyValue[0] = (string) $keyValue[0];
            $keyValue[1] = (string) $keyValue[1];

            if (strlen($keyValue[1]) == 0) {
                $keyValue[1] = '';
            }
            // We keep in the URL when Custom Variable have empty names
            // and values, as it means they can be deleted server side

            $customVariables[$index] = [
                'name' => self::truncateCustomVariableName($keyValue[0]),
                'value' => self::truncateCustomVariableValue($keyValue[1]),
            ];
        }

        return $customVariables;
    }
}
