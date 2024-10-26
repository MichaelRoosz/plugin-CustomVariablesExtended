<?php

namespace Piwik\Plugins\CustomVariablesExtended;

use Piwik\Common;
use Piwik\Plugin;
use Piwik\DataAccess\LogAggregator;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Metrics;

class LogAggregatorProxy extends LogAggregator
{
    protected $logAggregator;

    public function __construct($logAggregator)
    {
        $this->logAggregator = $logAggregator;
    }

    /**
     * Executes and returns a query aggregating visit logs, optionally grouping by some dimension. Returns
     * a DB statement that can be used to iterate over the result
     *
     * **Result Set**
     *
     * The following columns are in each row of the result set:
     *
     * - **{@link \Piwik\Metrics::INDEX_NB_UNIQ_VISITORS}**: The total number of unique visitors in this group
     *                                                      of aggregated visits.
     * - **{@link \Piwik\Metrics::INDEX_NB_VISITS}**: The total number of visits aggregated.
     * - **{@link \Piwik\Metrics::INDEX_NB_ACTIONS}**: The total number of actions performed in this group of
     *                                                aggregated visits.
     * - **{@link \Piwik\Metrics::INDEX_MAX_ACTIONS}**: The maximum actions performed in one visit for this group of
     *                                                 visits.
     * - **{@link \Piwik\Metrics::INDEX_SUM_VISIT_LENGTH}**: The total amount of time spent on the site for this
     *                                                      group of visits.
     * - **{@link \Piwik\Metrics::INDEX_BOUNCE_COUNT}**: The total number of bounced visits in this group of
     *                                                  visits.
     * - **{@link \Piwik\Metrics::INDEX_NB_VISITS_CONVERTED}**: The total number of visits for which at least one
     *                                                         conversion occurred, for this group of visits.
     *
     * Additional data can be selected by setting the `$additionalSelects` parameter.
     *
     * _Note: The metrics returned by this query can be customized by the `$metrics` parameter._
     *
     * @param array|string $dimensions `SELECT` fields (or just one field) that will be grouped by,
     *                                 eg, `'referrer_name'` or `array('referrer_name', 'referrer_keyword')`.
     *                                 The metrics retrieved from the query will be specific to combinations
     *                                 of these fields. So if `array('referrer_name', 'referrer_keyword')`
     *                                 is supplied, the query will aggregate visits for each referrer/keyword
     *                                 combination.
     * @param bool|string $where Additional condition for the `WHERE` clause. Can be used to filter
     *                           the set of visits that are considered for aggregation.
     * @param array $additionalSelects Additional `SELECT` fields that are not included in the group by
     *                                 clause. These can be aggregate expressions, eg, `SUM(somecol)`.
     * @param bool|array $metrics The set of metrics to calculate and return. If false, the query will select
     *                            all of them. The following values can be used:
     *
     *                            - {@link \Piwik\Metrics::INDEX_NB_UNIQ_VISITORS}
     *                            - {@link \Piwik\Metrics::INDEX_NB_VISITS}
     *                            - {@link \Piwik\Metrics::INDEX_NB_ACTIONS}
     *                            - {@link \Piwik\Metrics::INDEX_MAX_ACTIONS}
     *                            - {@link \Piwik\Metrics::INDEX_SUM_VISIT_LENGTH}
     *                            - {@link \Piwik\Metrics::INDEX_BOUNCE_COUNT}
     *                            - {@link \Piwik\Metrics::INDEX_NB_VISITS_CONVERTED}
     * @param bool|\Piwik\RankingQuery $rankingQuery
     *                                   A pre-configured ranking query instance that will be used to limit the result.
     *                                   If set, the return value is the array returned by {@link \Piwik\RankingQuery::execute()}.
     * @param bool|string $orderBy       Order By clause to add (e.g. user_id ASC)
     * @param int $timeLimit             Adds a MAX_EXECUTION_TIME query hint to the query if $timeLimit > 0
     *                                   for more details see {@link DbHelper::addMaxExecutionTimeHintToQuery}
     *
     * @return mixed A Zend_Db_Statement if `$rankingQuery` isn't supplied, otherwise the result of
     *               {@link \Piwik\RankingQuery::execute()}. Read {@link queryVisitsByDimension() this}
     *               to see what aggregate data is calculated by the query.
     * @param bool $rankingQueryGenerate if `true`, generates a SQL query / bind array pair and returns it. If false, the
     *                                   ranking query SQL will be immediately executed and the results returned.
     * @api
     */
    public function queryVisitsByDimension(
        array $dimensions = [],
        $where = false,
        array $additionalSelects = [],
        $metrics = false,
        $rankingQuery = false,
        $orderBy = false,
        $timeLimit = -1,
        $rankingQueryGenerate = false,
        $extraFrom = [],
    ) {
        $query = $this->getQueryByDimensionSql(
            $dimensions,
            $where,
            $additionalSelects,
            $metrics,
            $rankingQuery,
            $orderBy,
            $timeLimit,
            $rankingQueryGenerate,
            $extraFrom
        );

        // Ranking queries will return the data directly
        if ($rankingQuery && !$rankingQueryGenerate) {
            return $query;
        }

        file_put_contents('/srv/app/sql_log.txt', $query['sql'] . PHP_EOL, FILE_APPEND);

        return $this->logAggregator->getDb()->query($query['sql'], $query['bind']);
    }

    /**
     * Build the sql query used to query dimension data
     *
     * @param array                     $dimensions
     * @param bool|string               $where
     * @param array                     $additionalSelects
     * @param bool|array                $metrics
     * @param bool|\Piwik\RankingQuery  $rankingQuery
     * @param bool|string               $orderBy
     * @param int                       $timeLimit
     * @param bool                      $rankingQueryGenerate
     *
     * @return array
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function getQueryByDimensionSql(
        array $dimensions,
        $where,
        array $additionalSelects,
        $metrics,
        $rankingQuery,
        $orderBy,
        $timeLimit,
        $rankingQueryGenerate,
        $extraFrom = []
    ): array {
        $tableName = LogAggregator::LOG_VISIT_TABLE;
        $availableMetrics = $this->logAggregator->getVisitsMetricFields();

        $select  = $this->logAggregator->getSelectStatement($dimensions, $tableName, $additionalSelects, $availableMetrics, $metrics);
        $from    = array_merge([$tableName], $extraFrom);

        $where   = $this->logAggregator->getWhereStatement($tableName, LogAggregator::VISIT_DATETIME_FIELD, $where);
        $groupBy = $this->logAggregator->getGroupByStatement($dimensions, $tableName);
        $orderBys = $orderBy ? [$orderBy] : [];

        if ($rankingQuery) {
            $orderBys[] = '`' . Metrics::INDEX_NB_VISITS . '` DESC';
        }

        $query = $this->logAggregator->generateQuery($select, $from, $where, $groupBy, implode(', ', $orderBys));

        if ($rankingQuery) {
            unset($availableMetrics[Metrics::INDEX_MAX_ACTIONS]);

            // INDEX_NB_UNIQ_FINGERPRINTS is only processed if specifically asked for
            if (!$this->logAggregator->isMetricRequested(Metrics::INDEX_NB_UNIQ_FINGERPRINTS, $metrics)) {
                unset($availableMetrics[Metrics::INDEX_NB_UNIQ_FINGERPRINTS]);
            }

            $sumColumns = array_keys($availableMetrics);

            if ($metrics) {
                $sumColumns = array_intersect($sumColumns, $metrics);
            }

            $rankingQuery->addColumn($sumColumns, 'sum');
            if ($this->logAggregator->isMetricRequested(Metrics::INDEX_MAX_ACTIONS, $metrics)) {
                $rankingQuery->addColumn(Metrics::INDEX_MAX_ACTIONS, 'max');
            }

            if ($rankingQueryGenerate) {
                $query['sql'] = $rankingQuery->generateRankingQuery($query['sql']);
            } else {
                return $rankingQuery->execute($query['sql'], $query['bind'], $timeLimit);
            }
        }

        $query['sql'] = DbHelper::addMaxExecutionTimeHintToQuery($query['sql'], $timeLimit);

        return $query;
    }


    /**
     * Executes and returns a query aggregating action data (everything in the log_action table) and returns
     * a DB statement that can be used to iterate over the result
     *
     * <a name="queryActionsByDimension-result-set"></a>
     * **Result Set**
     *
     * Each row of the result set represents an aggregated group of actions. The following columns
     * are in each aggregate row:
     *
     * - **{@link Piwik\Metrics::INDEX_NB_UNIQ_VISITORS}**: The total number of unique visitors that performed
     *                                             the actions in this group.
     * - **{@link Piwik\Metrics::INDEX_NB_VISITS}**: The total number of visits these actions belong to.
     * - **{@link Piwik\Metrics::INDEX_NB_ACTIONS}**: The total number of actions in this aggregate group.
     *
     * Additional data can be selected through the `$additionalSelects` parameter.
     *
     * _Note: The metrics calculated by this query can be customized by the `$metrics` parameter._
     *
     * @param array|string $dimensions One or more SELECT fields that will be used to group the log_action
     *                                 rows by. This parameter determines which log_action rows will be
     *                                 aggregated together.
     * @param bool|string $where Additional condition for the WHERE clause. Can be used to filter
     *                           the set of visits that are considered for aggregation.
     * @param array $additionalSelects Additional SELECT fields that are not included in the group by
     *                                 clause. These can be aggregate expressions, eg, `SUM(somecol)`.
     * @param bool|array $metrics The set of metrics to calculate and return. If `false`, the query will select
     *                            all of them. The following values can be used:
     *
     *                              - {@link Piwik\Metrics::INDEX_NB_UNIQ_VISITORS}
     *                              - {@link Piwik\Metrics::INDEX_NB_VISITS}
     *                              - {@link Piwik\Metrics::INDEX_NB_ACTIONS}
     * @param bool|\Piwik\RankingQuery $rankingQuery
     *                                   A pre-configured ranking query instance that will be used to limit the result.
     *                                   If set, the return value is the array returned by {@link Piwik\RankingQuery::execute()}.
     * @param bool|string $joinLogActionOnColumn One or more columns from the **log_link_visit_action** table that
     *                                           log_action should be joined on. The table alias used for each join
     *                                           is `"log_action$i"` where `$i` is the index of the column in this
     *                                           array.
     *
     *                                           If a string is used for this parameter, the table alias is not
     *                                           suffixed (since there is only one column).
     * @param string $secondaryOrderBy      A secondary order by clause for the ranking query
     * @param int $timeLimit                Adds a MAX_EXECUTION_TIME hint to the query if $timeLimit > 0
     *                                      for more details see {@link DbHelper::addMaxExecutionTimeHintToQuery}
     * @return mixed A Zend_Db_Statement if `$rankingQuery` isn't supplied, otherwise the result of
     *               {@link Piwik\RankingQuery::execute()}. Read [this](#queryEcommerceItems-result-set)
     *               to see what aggregate data is calculated by the query.
     * @api
     */
    public function queryActionsByDimension(
        $dimensions,
        $where = '',
        $additionalSelects = array(),
        $metrics = false,
        $rankingQuery = null,
        $joinLogActionOnColumn = false,
        $secondaryOrderBy = null,
        $timeLimit = -1,
        $extraFrom = []
    ) {
        $tableName = LogAggregator::LOG_ACTIONS_TABLE;
        $availableMetrics = $this->logAggregator->getActionsMetricFields();

        $select  = $this->logAggregator->getSelectStatement($dimensions, $tableName, $additionalSelects, $availableMetrics, $metrics);
        $from    = array_merge([$tableName], $extraFrom);
        $where   = $this->logAggregator->getWhereStatement($tableName, LogAggregator::ACTION_DATETIME_FIELD, $where);
        $groupBy = $this->logAggregator->getGroupByStatement($dimensions, $tableName);

        if ($joinLogActionOnColumn !== false) {
            $multiJoin = is_array($joinLogActionOnColumn);
            if (!$multiJoin) {
                $joinLogActionOnColumn = array($joinLogActionOnColumn);
            }

            foreach ($joinLogActionOnColumn as $i => $joinColumn) {
                $tableAlias = 'log_action' . ($multiJoin ? $i + 1 : '');

                if (strpos($joinColumn, ' ') === false) {
                    $joinOn = $tableAlias . '.idaction = ' . $tableName . '.' . $joinColumn;
                } else {
                    // more complex join column like if (...)
                    $joinOn = $tableAlias . '.idaction = ' . $joinColumn;
                }

                $from[] = array(
                    'table'      => 'log_action',
                    'tableAlias' => $tableAlias,
                    'joinOn'     => $joinOn
                );
            }
        }

        $orderBy = false;
        if ($rankingQuery) {
            $orderBy = '`' . Metrics::INDEX_NB_ACTIONS . '` DESC';
            if ($secondaryOrderBy) {
                $orderBy .= ', ' . $secondaryOrderBy;
            }
        }

        $query = $this->logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy);

        if ($rankingQuery) {
            $sumColumns = array_keys($availableMetrics);
            if ($metrics) {
                $sumColumns = array_intersect($sumColumns, $metrics);
            }

            $rankingQuery->addColumn($sumColumns, 'sum');

            return $rankingQuery->execute($query['sql'], $query['bind'], $timeLimit);
        }

        $query['sql'] = DbHelper::addMaxExecutionTimeHintToQuery($query['sql'], $timeLimit);

        return $this->logAggregator->getDb()->query($query['sql'], $query['bind']);
    }
}
