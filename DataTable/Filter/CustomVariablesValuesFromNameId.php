<?php

namespace Piwik\Plugins\CustomVariablesExtended\DataTable\Filter;

use Piwik\DataTable\BaseFilter;
use Piwik\DataTable;
use Piwik\Piwik;

class CustomVariablesValuesFromNameId extends BaseFilter {
    public function __construct($table) {
        parent::__construct($table);
    }

    /**
     * @param DataTable $table
     */
    public function filter($table): void {
        $notDefinedLabel = Piwik::translate('General_NotDefined', Piwik::translate('CustomVariablesExtended_ColumnCustomVariableValue'));

        $table->queueFilter('ColumnCallbackReplace', ['label', function ($label) use ($notDefinedLabel) {
            return $label === \Piwik\Plugins\CustomVariablesExtended\Archiver::LABEL_CUSTOM_VALUE_NOT_DEFINED
                ? $notDefinedLabel
                : strval($label);
        }]);
    }
}
