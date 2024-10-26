<?php

namespace Piwik\Plugins\CustomVariablesExtended\Tracker\LogTable;

use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableLinkVisitAction;
use Piwik\Tracker\LogTable;

class CustomVariableLinkVisitAction extends LogTable
{
    public function getName()
    {
        return LogTableLinkVisitAction::TABLE_NAME;
    }

    public function getIdColumn()
    {
        return 'idlink_va';
    }

    public function getColumnToJoinOnIdVisit()
    {
        return 'idvisit';
    }

    public function getColumnToJoinOnIdAction()
    {
        return '';
    }

    public function getWaysToJoinToOtherLogTables()
    {
        return [];
    }

    public function shouldJoinWithSubSelect()
    {
        return false;
    }

    public function getDateTimeColumn()
    {
        return '';
    }

    public function getLinkTableToBeAbleToJoinOnVisit()
    {
        return;
    }

    public function getPrimaryKey()
    {
        return ['idlink_va', 'index'];
    }
}
