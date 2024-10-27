<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link http://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomVariablesExtended\Tracker\LogTable;

use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableVisit;
use Piwik\Tracker\LogTable;

class CustomVariableVisit extends LogTable {
    public function getName(): string {
        return LogTableVisit::TABLE_NAME;
    }

    public function getIdColumn(): string {
        return 'idvisit';
    }

    public function getColumnToJoinOnIdVisit(): string {
        return 'idvisit';
    }

    public function getColumnToJoinOnIdAction(): string {
        return '';
    }

    public function shouldJoinWithSubSelect(): bool {
        return false;
    }

    public function getDateTimeColumn(): string {
        return '';
    }

    /**
     * @return string[]
     */
    public function getPrimaryKey(): array {
        return ['idvisit', 'index'];
    }
}
