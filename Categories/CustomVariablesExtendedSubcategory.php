<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link http://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CustomVariablesExtended\Categories;

use Piwik\Category\Subcategory;
use Piwik\Piwik;

class CustomVariablesExtendedSubcategory extends Subcategory {
    protected $categoryId = 'General_Visitors';
    protected $id = 'CustomVariablesExtended_CustomVariables';
    protected $order = 45;

    /** @phpstan-ignore method.childReturnType  */
    public function getHelp(): string {
        return '<p>' . Piwik::translate('CustomVariablesExtended_CustomVariablesSubcategoryHelp1') . '</p>'
            . '<p><a href="https://matomo.org/docs/custom-variables/" rel="noreferrer noopener" target="_blank">' . Piwik::translate('CustomVariablesExtended_CustomVariablesSubcategoryHelp2') . '</a></p>'
        ;
    }
}
