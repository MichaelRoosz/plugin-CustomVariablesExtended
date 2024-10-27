<?php

namespace Piwik\Plugins\CustomVariablesExtended;

use Piwik\API\Request;
use Piwik\Archive;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Metrics;
use Piwik\Piwik;

/**
 * @method static \Piwik\Plugins\CustomVariablesExtended\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * @param int $idSite
     * @param string $period
     * @param Date $date
     * @param string|false $segment
     * @param bool $flat
     * @param bool $expanded
     * @param int|null $idSubtable
     *
     * @return DataTable|DataTable\Map
     */
    protected function getDataTable($idSite, $period, $date, $segment, $expanded, $flat, $idSubtable)
    {
        /** @phpstan-ignore argument.type */
        $dataTable = Archive::createDataTableFromArchive(Archiver::CUSTOM_VARIABLE_RECORD_NAME, $idSite, $period, $date, $segment, $expanded, $flat, $idSubtable);
        $dataTable->queueFilter('ColumnDelete', ['nb_uniq_visitors']);

        if ($flat) {
            $dataTable->filterSubtables('Sort', array(Metrics::INDEX_NB_ACTIONS, 'desc', $naturalSort = false, $expanded));
            $dataTable->queueFilterSubtables('ColumnDelete', ['nb_uniq_visitors']);
        }

        return $dataTable;
    }

    /**
     * @param int $idSite
     * @param string $period
     * @param Date $date
     * @param string|false $segment
     * @param bool $expanded
     * @param bool $_leavePiwikCoreVariables
     * @param bool $flat
     *
     * @return DataTable|DataTable\Map
     */
    public function getCustomVariables($idSite, $period, $date, $segment = false, $expanded = false, $_leavePiwikCoreVariables = false, $flat = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $dataTable = $this->getDataTable($idSite, $period, $date, $segment, $expanded, $flat, $idSubtable = null);

        if ($dataTable instanceof DataTable
            && !$_leavePiwikCoreVariables
        ) {
            $mapping = self::getReservedCustomVariableKeys();
            foreach ($mapping as $name) {
                $row = $dataTable->getRowFromLabel($name);
                if ($row) {
                    $dataTable->deleteRow($dataTable->getRowIdFromLabel($name));
                }
            }
        }


        if ($flat) {
            $dataTable->filterSubtables('Piwik\Plugins\CustomVariablesExtended\DataTable\Filter\CustomVariablesValuesFromNameId');
        } else {
            $dataTable->filter('AddSegmentByLabel', array('customVariableName'));
        }

        return $dataTable;
    }

    /**
     * @return array<string>
     */
    public static function getReservedCustomVariableKeys(): array
    {
        // Note: _pk_scat and _pk_scount has been used for site search, but aren't in use anymore
        return array('_pks', '_pkn', '_pkc', '_pkp', '_pk_scat', '_pk_scount');
    }

    /**
     * @param int $idSite
     * @param string $period
     * @param Date $date
     * @param int $idSubtable
     * @param string|false $segment
     * @param bool $_leavePriceViewedColumn
     *
     * @return DataTable|DataTable\Map
     */
    public function getCustomVariablesValuesFromNameId($idSite, $period, $date, $idSubtable, $segment = false, $_leavePriceViewedColumn = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $dataTable = $this->getDataTable($idSite, $period, $date, $segment, $expanded = false, $flat = false, $idSubtable);

        if (!$_leavePriceViewedColumn) {
            $dataTable->deleteColumn('price_viewed');
        } else {
            // Hack Ecommerce product price tracking to display correctly
            $dataTable->renameColumn('price_viewed', 'price');
        }

        $dataTable->filter('Piwik\Plugins\CustomVariablesExtended\DataTable\Filter\CustomVariablesValuesFromNameId');

        return $dataTable;
    }

    /**
     * Get a list of all available custom variable slots (scope + index) and which names have been used so far in
     * each slot since the beginning of the website.
     *
     * @return array<array{
     *   scope: string,
     *   index: int,
     *   usages: array<array{
     *     name: string,
     *     nb_visits: int,
     *     nb_actions: int
     *   }>
     * }>
     */
    public function getUsagesOfSlots(int $idSite): array
    {
        Piwik::checkUserHasAdminAccess($idSite);

        $firstIndex = CustomVariablesExtended::FIRST_CUSTOM_VARIABLE_INDEX;
        $lastIndex = CustomVariablesExtended::LAST_CUSTOM_VARIABLE_INDEX;
        $cvarCount = $lastIndex - $firstIndex + 1;

        $usedCustomVariables = [
            'visit' => array_fill($firstIndex, $cvarCount, []),
            'page'  => array_fill($firstIndex, $cvarCount, []),
        ];

        $today = StaticContainer::get('CustomVariablesExtended.today');
        $date = '2008-12-12,' . $today;

        /** @var \Piwik\DataTable $customVarUsages */
        $customVarUsages = Request::processRequest(
            'CustomVariablesExtended.getCustomVariables',
            [
                'idSite' => $idSite,
                'period' => 'range',
                'date' => $date,
                'format' => 'original'
            ]
        );

        foreach ($customVarUsages->getRows() as $row) {
            $slots = $row->getMetadata('slots');

            if ($slots && is_array($slots)) {

                $name = $row->getColumn('label');
                if (!is_string($name)) {
                    continue;
                }

                $nbVisits = $row->getColumn('nb_visits');
                if (!is_int($nbVisits)) {
                    continue;
                }

                $nbActions = $row->getColumn('nb_actions');
                if (!is_int($nbActions)) {
                    continue;
                }

                foreach ($slots as $slot) {
                    $scope = (string)$slot['scope'];
                    $index = (int)$slot['index'];

                    $usedCustomVariables[$scope][$index][] = [
                        'name' => $name,
                        'nb_visits' => $nbVisits,
                        'nb_actions' => $nbActions,
                    ];
                }
            }
        }

        $grouped = [];
        foreach ($usedCustomVariables as $scope => $scopes) {
            foreach ($scopes as $index => $cvars) {
                $grouped[] = [
                    'scope' => $scope,
                    'index' => $index,
                    'usages' => $cvars
                ];
            }
        }

        return $grouped;
    }
}
