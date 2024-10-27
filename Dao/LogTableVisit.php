<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link http://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomVariablesExtended\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class LogTableVisit {
    public const TABLE_NAME = 'log_custom_variable_visit';

    /** @var string $tableName */
    private $tableName = self::TABLE_NAME;

    /** @var string $tableNamePrefixed */
    private $tableNamePrefixed;

    public function __construct() {
        $this->tableNamePrefixed = Common::prefixTable($this->tableName);
    }

    public function insertCustomVariable(int $idSite, int $idVisit, int $index, string $name, string $value): void {
        $this->getDb()->query(
            'INSERT INTO ' . $this->tableNamePrefixed
                . ' (`idvisit`, `index`,  `idsite`, `name`, `value`)'
                . ' VALUES (?,?,?,?,?)'
                . ' ON DUPLICATE KEY UPDATE '
                . ' `idsite` = ?,'
                . ' `name` = ?,'
                . ' `value` = ?',
            [
                $idVisit,
                $index,
                $idSite,
                $name,
                $value,
                $idSite,
                $name,
                $value,
            ]
        );
    }

    /**
     * @return array<string, array{name: string, value: string}>
     */
    public function getCustomVariablesForVisit(string $idVisit): array {
        $sql = 'SELECT * FROM ' . $this->tableNamePrefixed . ' WHERE idvisit = ?';

        $raw = $this->getDb()->fetchAll($sql, [$idVisit]);

        $cvars = [];
        foreach ($raw as $row) {
            $cvars['custom_var_k' . $row['index']] = [
                'name' => $row['name'],
                'value' => $row['value'],
            ];
        }

        return $cvars;
    }

    public function install(): void {
        $table = '`idvisit` BIGINT UNSIGNED NOT NULL,
                  `index` SMALLINT UNSIGNED NOT NULL,
                  `idsite` INT UNSIGNED NOT NULL,
                  `name` VARCHAR(' . CustomVariablesExtended::MAX_LENGTH_VARIABLE_NAME . ') DEFAULT NULL,
                  `value` VARCHAR(' . CustomVariablesExtended::MAX_LENGTH_VARIABLE_VALUE . ') DEFAULT NULL,
                  PRIMARY KEY (`idvisit`, `index`),
                  KEY (`idsite`, `index`, `name`, `idvisit`)';

        DbHelper::createTable($this->tableName, $table);
    }

    public function uninstall(): void {
        Db::dropTables([$this->tableNamePrefixed]);
    }

    /**
     * @return \Piwik\Tracker\Db|\Piwik\Db
     */
    private function getDb() {
        $db = Db::get();

        if ($db instanceof \Piwik\Tracker\Db) {
            return $db;
        }

        if ($db instanceof \Piwik\Db) {
            return $db;
        }

        throw new \Exception('Unsupported database type');
    }
}
