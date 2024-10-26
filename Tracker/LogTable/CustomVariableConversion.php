<?php

namespace Piwik\Plugins\CustomVariablesExtended\Tracker\LogTable;

use Piwik\Plugins\CustomVariablesExtended\Dao\LogTableConversion;
use Piwik\Tracker\LogTable;

class CustomVariableConversion extends LogTable
{
    public function getName()
    {
        return LogTableConversion::TABLE_NAME;
    }

    public function getIdColumn()
    {
        return 'idvisit';
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
        return ['idvisit', 'idgoal', 'buster', 'scope', 'index'];
    }

}
