<?php

namespace Piwik\Plugins\CustomVariablesExtended\Tracker\LogTable;

use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableLinkVisitAction;
use Piwik\Tracker\LogTable;

class CustomVariableLinkVisitAction extends LogTable
{
    public function getName(): string
    {
        return LogTableLinkVisitAction::TABLE_NAME;
    }

    public function getIdColumn(): string
    {
        return 'idlink_va';
    }

    public function getColumnToJoinOnIdVisit(): string
    {
        return 'idvisit';
    }

    public function getColumnToJoinOnIdAction(): string
    {
        return '';
    }

    public function shouldJoinWithSubSelect(): bool
    {
        return false;
    }

    public function getDateTimeColumn(): string
    {
        return '';
    }

    /**
     * @return string[]
     */
    public function getPrimaryKey(): array
    {
        return ['idlink_va', 'index'];
    }
}
