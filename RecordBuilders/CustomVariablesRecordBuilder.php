<?php

namespace Piwik\Plugins\CustomVariablesExtended\RecordBuilders;

use Exception;
use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Record;
use Piwik\ArchiveProcessor\RecordBuilder;
use Piwik\Config;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable;
use Piwik\DbHelper;
use Piwik\Metrics;
use Piwik\Plugins\CustomVariablesExtended\API;
use Piwik\Plugins\CustomVariablesExtended\Archiver;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableConversion;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableLinkVisitAction;
use Piwik\Plugins\CustomVariablesExtended\LogAggregatorProxy;
use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableVisit;
use Piwik\Tracker\GoalManager;
use Zend_Db_Statement;

class CustomVariablesRecordBuilder extends RecordBuilder
{
    public function __construct()
    {
        parent::__construct();

        $this->maxRowsInTable = Config::getInstance()->General['datatable_archiving_maximum_rows_custom_variables']
            /** @phpstan-ignore property.notFound */
            ?? Config::getInstance()->General['datatable_archiving_maximum_rows_custom_dimensions'];

        $this->maxRowsInSubtable = Config::getInstance()->General['datatable_archiving_maximum_rows_subtable_custom_variables']
            /** @phpstan-ignore property.notFound */
            ?? Config::getInstance()->General['datatable_archiving_maximum_rows_subtable_custom_dimensions'];

        /** @phpstan-ignore assign.propertyType */
        $this->columnToSortByBeforeTruncation = Metrics::INDEX_NB_VISITS;

        $this->columnAggregationOps = ['slots' => 'uniquearraymerge'];
    }

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        return [
            Record::make(Record::TYPE_BLOB, Archiver::CUSTOM_VARIABLE_RECORD_NAME),
        ];
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $record = new DataTable();
        $metadata = [];
        $metadataFlat = [];

        $logAggregator = $archiveProcessor->getLogAggregator();

        for ($i = CustomVariablesExtended::FIRST_CUSTOM_VARIABLE_INDEX; $i <= CustomVariablesExtended::LAST_CUSTOM_VARIABLE_INDEX; $i++) {
            $this->aggregateCustomVariable($record, $metadata, $metadataFlat, $logAggregator, $i);
        }

        $this->removeVisitsMetricsFromActionsAggregate($record);
        $record->filter(DataTable\Filter\EnrichRecordWithGoalMetricSums::class);

        foreach ($record->getRows() as $row) {
            $label = $row->getColumn('label');
            if (!empty($metadata[$label])) {
                foreach ($metadata[$label] as $name => $value) {
                    $row->addMetadata($name, $value);
                }
            }
        }

        return [Archiver::CUSTOM_VARIABLE_RECORD_NAME => $record];
    }

    /**
     * @param array<string, array{
     *   slots: array<array{
     *     scope: string,
     *     index: int
     *   }>
     * }> $metadata
     *
     * @param array<string, bool> $metadataFlat
     */
    protected function aggregateCustomVariable(DataTable $record, array &$metadata, array &$metadataFlat, LogAggregator $logAggregator, int $index): void
    {
        $logAggregatorProxy = new LogAggregatorProxy($logAggregator);

        $keyField = 'custom_var_k' . $index;
        $valueField = 'custom_var_v' . $index;

        $query = $this->queryVisitsByDimension($logAggregatorProxy, $index);
        $this->aggregateFromVisits($record, $metadata, $metadataFlat, $query, $keyField, $valueField);

        $query = $this->queryActionsByDimension($logAggregatorProxy, $index);
        $this->aggregateFromActions($record, $metadata, $metadataFlat, $query, $keyField, $valueField);

        $query = $this->queryConversionsByDimension($logAggregator, $index);
        $this->aggregateFromConversions($record, $query, $keyField, $valueField);
    }

    /**
     * @param array<string, array{
     *   slots: array<array{
     *     scope: string,
     *     index: int
     *   }>
     * }> $metadata
     *
     * @param array<string, bool> $metadataFlat
     */
    protected function aggregateFromVisits(
        DataTable $record,
        array &$metadata,
        array &$metadataFlat,
        Zend_Db_Statement $query,
        string $keyField,
        string $valueField
    ): void {
        while ($row = $query->fetch()) {

            if (!is_array($row)) {
                continue;
            }

            $key = $row[$keyField];
            $value = $this->cleanCustomVarValue($row[$valueField]);

            $this->addMetadata($metadata, $metadataFlat, $keyField, $key, CustomVariablesExtended::SCOPE_VISIT);

            $columns = [
                Metrics::INDEX_NB_UNIQ_VISITORS => $row[Metrics::INDEX_NB_UNIQ_VISITORS],
                Metrics::INDEX_NB_VISITS => $row[Metrics::INDEX_NB_VISITS],
                Metrics::INDEX_NB_ACTIONS => $row[Metrics::INDEX_NB_ACTIONS],
                Metrics::INDEX_NB_USERS => $row[Metrics::INDEX_NB_USERS],
                Metrics::INDEX_MAX_ACTIONS => $row[Metrics::INDEX_MAX_ACTIONS],
                Metrics::INDEX_SUM_VISIT_LENGTH => $row[Metrics::INDEX_SUM_VISIT_LENGTH],
                Metrics::INDEX_BOUNCE_COUNT => $row[Metrics::INDEX_BOUNCE_COUNT],
                Metrics::INDEX_NB_VISITS_CONVERTED => $row[Metrics::INDEX_NB_VISITS_CONVERTED],
            ];

            $existingRow = $record->getRowFromLabel($key);

            // Edge case fail safe
            if (!empty($existingRow)
                /** @phpstan-ignore argument.type */
                && !$existingRow->hasColumn(Metrics::INDEX_NB_VISITS)
            ) {
                continue;
            }

            // In case the existing Row had no action metrics (eg. Custom Variable XYZ with "visit" scope)
            // but the new Row has action metrics (eg. same Custom Variable XYZ this time with a "page" scope)
            if (!empty($existingRow)
                /** @phpstan-ignore argument.type */
                && !$existingRow->hasColumn(Metrics::INDEX_MAX_ACTIONS)
            ) {
                $toZero = [
                    Metrics::INDEX_NB_USERS,
                    Metrics::INDEX_MAX_ACTIONS,
                    Metrics::INDEX_SUM_VISIT_LENGTH,
                    Metrics::INDEX_BOUNCE_COUNT,
                    Metrics::INDEX_NB_VISITS_CONVERTED,
                ];
                foreach ($toZero as $metric) {
                    /** @phpstan-ignore argument.type */
                    $existingRow->setColumn($metric, 0);
                }
            }

            $topLevelRow = $record->sumRowWithLabel($key, $columns);
            $topLevelRow->sumRowWithLabelToSubtable($value, $columns);
        }
    }

    protected function cleanCustomVarValue(?string $value): string
    {
        if ($value !== null && strlen($value)) {
            return $value;
        }
        return Archiver::LABEL_CUSTOM_VALUE_NOT_DEFINED;
    }

    /**
     * @param array<string, array{
     *   slots: array<array{
     *     scope: string,
     *     index: int
     *   }>
     * }> $metadata
     *
     * @param array<string, bool> $metadataFlat
     */
    protected function aggregateFromActions(
        DataTable $record,
        array &$metadata,
        array &$metadataFlat,
        Zend_Db_Statement $query,
        string $keyField,
        string $valueField
    ): void {
        while ($row = $query->fetch()) {

            if (!is_array($row)) {
                continue;
            }

            $key = $row[$keyField];
            $value = $this->cleanCustomVarValue($row[$valueField]);

            $this->addMetadata($metadata, $metadataFlat, $keyField, $key, CustomVariablesExtended::SCOPE_PAGE);

            $alreadyAggregated = $this->aggregateEcommerceCategories($record, $key, $value, $row);
            if (!$alreadyAggregated) {
                $this->aggregateActionByKeyAndValue($record, $key, $value, $row);

                $columns = [
                    Metrics::INDEX_NB_UNIQ_VISITORS => $row[Metrics::INDEX_NB_UNIQ_VISITORS],
                    Metrics::INDEX_NB_VISITS => $row[Metrics::INDEX_NB_VISITS],
                    Metrics::INDEX_NB_ACTIONS => $row[Metrics::INDEX_NB_ACTIONS],
                ];
                $record->sumRowWithLabel($key, $columns);
            }
        }
    }

    /**
     * @param array<string, array{
     *   slots: array<array{
     *     scope: string,
     *     index: int
     *   }>
     * }> $metadata
     *
     * @param array<string, bool> $metadataFlat
     */
    private function addMetadata(array &$metadata, array &$metadataFlat, string $keyField, string $label, string $scope): void
    {
        $index = (int) str_replace('custom_var_k', '', $keyField);

        if (!array_key_exists($label, $metadata)) {
            $metadata[$label] = array('slots' => array());
        }

        $uniqueId = $label . 'scope' . $scope . 'index' . $index;

        if (!isset($metadataFlat[$uniqueId])) {
            $metadata[$label]['slots'][] = array('scope' => $scope, 'index' => $index);
            $metadataFlat[$uniqueId] = true;
        }
    }

    /**
     * @param string $key
     * @param string $value
     * @param array<mixed> $row
     * @return bool True if the $row metrics were already added to the ->metrics
     */
    protected function aggregateEcommerceCategories(DataTable $record, string $key, string $value, array $row): bool
    {
        $ecommerceCategoriesAggregated = false;
        if ($key == '_pkc'
            && $value[0] == '[' && $value[1] == '"'
        ) {
            // In case categories were truncated, try closing the array
            if (substr($value, -2) != '"]') {
                $value .= '"]';
            }
            $decoded = json_decode($value);
            if (is_array($decoded)) {
                $count = 0;
                foreach ($decoded as $category) {
                    if (empty($category)
                        || $count >= GoalManager::MAXIMUM_PRODUCT_CATEGORIES
                    ) {
                        continue;
                    }
                    $this->aggregateActionByKeyAndValue($record, $key, $category, $row);
                    $ecommerceCategoriesAggregated = true;
                    $count++;
                }
            }
        }
        return $ecommerceCategoriesAggregated;
    }

    /**
     * @param array<mixed> $row
     */
    protected function aggregateActionByKeyAndValue(DataTable $record, string $key, string $value, array $row): void
    {
        $columns = [
            Metrics::INDEX_NB_UNIQ_VISITORS => $row[Metrics::INDEX_NB_UNIQ_VISITORS],
            Metrics::INDEX_NB_VISITS => $row[Metrics::INDEX_NB_VISITS],
            Metrics::INDEX_NB_ACTIONS => $row[Metrics::INDEX_NB_ACTIONS],
        ];

        $toplevelRow = $record->sumRowWithLabel($key, []);

        // Edge case fail safe
        $subtable = $toplevelRow->getSubtable();
        $existingRow = !empty($subtable) && !is_bool($subtable) ? $subtable->getRowFromLabel($value) : null;
        if (!empty($existingRow)
            /** @phpstan-ignore argument.type */
            && !$existingRow->hasColumn(Metrics::INDEX_NB_VISITS)
        ) {
            return;
        }

        $subtableRow = $toplevelRow->sumRowWithLabelToSubtable($value, $columns);

        if ($this->isReservedKey($key)) {
            // Price tracking on Ecommerce product/category pages:
            // the average is returned from the SQL query so the price is not "summed" like other metrics
            $index = Metrics::INDEX_ECOMMERCE_ITEM_PRICE_VIEWED;
            if (!empty($row[$index]) && is_numeric($row[$index])) {
                /** @phpstan-ignore argument.type */
                $subtableRow->setColumn($index, (float)$row[$index]);
            }
        }
    }

    protected static function isReservedKey(string $key): bool
    {
        return in_array($key, API::getReservedCustomVariableKeys());
    }

    protected function aggregateFromConversions(DataTable $record, Zend_Db_Statement $query, string $keyField, string $valueField): void
    {
        while ($row = $query->fetch()) {

            if (!is_array($row)) {
                continue;
            }

            $key = $row[$keyField];

            $value = $this->cleanCustomVarValue($row[$valueField]);

            $idGoal = (int) $row['idgoal'];
            $columns = [
                Metrics::INDEX_GOALS => [
                    $idGoal => Metrics::makeGoalColumnsRow($idGoal, $row),
                ],
            ];

            $topLevelRow = $record->sumRowWithLabel($key, $columns);
            $topLevelRow->sumRowWithLabelToSubtable($value, $columns);
        }
    }

    /**
     * Delete Visit, Unique Visitor and Users metric from 'page' scope custom variables.
     *
     * - Custom variables of 'visit' scope: it is expected that these ones have the "visit" column set.
     * - Custom variables of 'page' scope: we cannot process "Visits" count for these.
     *   Why?
     *     "Actions" column is processed with a SELECT count(*).
     *     A same visit can set the same custom variable of 'page' scope multiple times.
     *     We cannot sum the values of count(*) as it would be incorrect.
     *     The way we could process "Visits" Metric for 'page' scope variable is to issue a count(Distinct *) or so,
     *     but it is no implemented yet (this would likely be very slow for high traffic sites).
     *
     */
    protected function removeVisitsMetricsFromActionsAggregate(DataTable $record): void
    {
        foreach ($record->getRows() as $row) {
            $label = $row->getColumn('label');
            if ((!is_string($label) || !self::isReservedKey($label))
                && $this->isActionsRow($row)
            ) {
                /** @phpstan-ignore argument.type */
                $row->deleteColumn(Metrics::INDEX_NB_UNIQ_VISITORS);

                /** @phpstan-ignore argument.type */
                $row->deleteColumn(Metrics::INDEX_NB_VISITS);

                /** @phpstan-ignore argument.type */
                $row->deleteColumn(Metrics::INDEX_NB_USERS);
            }
        }
    }

    private function isActionsRow(DataTable\Row $row): bool
    {
        /** @phpstan-ignore argument.type */
        return count($row->getColumns()) == 4 && $row->hasColumn(Metrics::INDEX_NB_ACTIONS);
    }

    public function queryVisitsByDimension(
        LogAggregatorProxy $logAggregatorProxy,
        int $index
    ): Zend_Db_Statement {
        $tableName = LogTableVisit::TABLE_NAME;

        $dimensions = [
            'custom_var_k' . $index => $tableName . '.name',
            'custom_var_v' . $index => $tableName . '.value',
        ];

        $where = $tableName . ".value != ''";

        $extraFrom = [
            [
                'table' => $tableName,
                'tableAlias' => $tableName,
                'joinOn' => $tableName . '.idvisit = ' . LogAggregator::LOG_VISIT_TABLE . '.idvisit'
                                . ' AND ' . $tableName . '.index = ' . $index,
            ],
        ];

        $query = $logAggregatorProxy->queryVisitsByDimension($dimensions, $where, [], false, false, false, -1, false, $extraFrom);
        if (!($query instanceof Zend_Db_Statement)) {
            throw new Exception('queryVisitsByDimension did not return a Zend_Db_Statement');
        }

        return $query;
    }

    public function queryActionsByDimension(
        LogAggregatorProxy $logAggregatorProxy,
        int $index
    ): Zend_Db_Statement {
        $tableName = LogTableLinkVisitAction::TABLE_NAME;

        $dimensions = [
            'custom_var_k' . $index => $tableName . '.name',
            'custom_var_v' . $index => $tableName . '.value',
        ];

        $where = $tableName . ".value != ''";

        $extraFrom = [
            [
                'table' => $tableName,
                'tableAlias' => $tableName,
                'joinOn' => $tableName . '.idlink_va = ' . LogAggregator::LOG_ACTIONS_TABLE . '.idlink_va'
                                . ' AND ' . $tableName . '.index = ' . $index,
            ],
        ];

        $query = $logAggregatorProxy->queryActionsByDimension($dimensions, $where, [], false, null, false, null, -1, $extraFrom);
        if (!($query instanceof Zend_Db_Statement)) {
            throw new Exception('queryActionsByDimension did not return a Zend_Db_Statement');
        }

        return $query;
    }

    public function queryConversionsByDimension(
        LogAggregator $logAggregator,
        int $index
    ): Zend_Db_Statement {
        $tableName = LogTableConversion::TABLE_NAME;

        $dimensions = [
            'custom_var_k' . $index => $tableName . '.name',
            'custom_var_v' . $index => $tableName . '.value',
        ];

        $where = $tableName . ".value != ''";

        $extraFrom = [
            [
                'table' => $tableName,
                'tableAlias' => $tableName,
                'joinOn' => $tableName . '.idvisit = ' . LogAggregator::LOG_CONVERSION_TABLE . '.idvisit'
                                . ' AND ' . $tableName . '.idgoal = ' . LogAggregator::LOG_CONVERSION_TABLE . '.idgoal'
                                . ' AND ' . $tableName . '.buster = ' . LogAggregator::LOG_CONVERSION_TABLE . '.buster'
                                . ' AND ' . $tableName . '.scope = ' . CustomVariablesExtended::SCOPE_ID_VISIT
                                . ' AND ' . $tableName . '.index = ' . $index,
            ],
        ];

        $query = $logAggregator->queryConversionsByDimension($dimensions, $where, [], $extraFrom);
        if (!($query instanceof Zend_Db_Statement)) {
            throw new Exception('queryConversionsByDimension did not return a Zend_Db_Statement');
        }

        return $query;
    }
}
