<?php

namespace Piwik\Plugins\CustomVariablesExtended;

use Exception;
use Piwik\Piwik;
use Piwik\Columns\Dimension;
use Piwik\Columns\DimensionSegmentFactory;
use Piwik\Columns\Discriminator;
use Piwik\Plugins\CustomVariablesExtended\Columns\Join\CustomVariableLinkVisitActionJoin;
use Piwik\Plugins\CustomVariablesExtended\Columns\Join\CustomVariableVisitJoin;
use Piwik\Segment\SegmentsList;
use Piwik\Plugins\CustomVariablesExtended\Segment;
use Piwik\Plugins\CustomVariablesExtended\CustomVariablesExtended;

class CustomVariableDimension extends Dimension
{
    protected $type = self::TYPE_TEXT;

    private $id;

    private $cvScope;
    private $cvIndex;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return Piwik::translate('CustomVariablesExtended_ColumnCustomVariableValue');
    }

    public function initCustomDimension($scope, $index)
    {
        $this->cvScope = $scope;
        $this->cvIndex = $index;

        $category = $this->getScopeDescription();

        $this->id = 'CustomVariablesExtended.CustomVariable' . $this->getScopeName() . $index;
        $this->nameSingular = Piwik::translate('CustomVariablesExtended_ColumnCustomVariableValue') . ' ' . $index . ' (' . $category .')';
        $this->category = 'CustomVariablesExtended_CustomVariables';
    }

    public function configureSegments(SegmentsList $segmentsList, DimensionSegmentFactory $dimensionSegmentFactory)
    {
        if ($this->cvScope === CustomVariablesExtended::SCOPE_CONVERSION) {
            return;
        }

        $baseName = 'customVariable';
        if ($this->cvScope === CustomVariablesExtended::SCOPE_PAGE) {
            $baseName .= 'Page';
        }

        if ($this->cvScope === CustomVariablesExtended::SCOPE_VISIT) {
            $sqlSegment = 'log_visit.idvisit';
        } else {
            $sqlSegment = 'log_link_visit_action.idlink_va';
        }

        $segment = new Segment();
        $segment->setSegment($baseName . 'Name' . $this->cvIndex);
        $segment->setSqlSegment($sqlSegment);
        $segment->setName(Piwik::translate('CustomVariablesExtended_ColumnCustomVariableName') . ' ' . $this->cvIndex
                . ' (' . Piwik::translate('CustomVariablesExtended_Scope' . $this->getScopeName()) . ')');
        $segmentsList->addSegment($dimensionSegmentFactory->createSegment($segment));

        $segment = new Segment();
        $segment->setSegment($baseName . 'Value' . $this->cvIndex);
        $segment->setSqlSegment($sqlSegment);
        $segment->setName(Piwik::translate('CustomVariablesExtended_ColumnCustomVariableValue') . ' ' . $this->cvIndex
                . ' (' . Piwik::translate('CustomVariablesExtended_Scope' . $this->getScopeName()) . ')');
        $segmentsList->addSegment($dimensionSegmentFactory->createSegment($segment));
    }

    public function getDbColumnJoin()
    {
        if ($this->cvScope === CustomVariablesExtended::SCOPE_VISIT) {
            return new CustomVariableVisitJoin();
        } elseif ($this->cvScope === CustomVariablesExtended::SCOPE_PAGE) {
            return new CustomVariableLinkVisitActionJoin();
        } else {
            throw new Exception('Unsupported scope for db column join: ' . $this->cvScope);
        }
    }

    public function getDbDiscriminator()
    {
        if ($this->cvScope === CustomVariablesExtended::SCOPE_VISIT) {
            return new Discriminator('log_custom_variable_visit', 'index', $this->cvIndex);
        } elseif ($this->cvScope === CustomVariablesExtended::SCOPE_PAGE) {
            return new Discriminator('log_custom_variable_link_va', 'index', $this->cvIndex);
        } else {
            throw new Exception('Unsupported scope for db discriminator: ' . $this->cvScope);
        }
    }

    private function getScopeName()
    {
        return ucfirst($this->cvScope);
    }

    private function getScopeDescription()
    {
        switch ($this->cvScope) {
            case CustomVariablesExtended::SCOPE_PAGE:
                return Piwik::translate('CustomVariablesExtended_ScopePage');
            case CustomVariablesExtended::SCOPE_VISIT:
                return Piwik::translate('CustomVariablesExtended_ScopeVisit');
            case CustomVariablesExtended::SCOPE_CONVERSION:
                return Piwik::translate('CustomVariablesExtended_ScopeConversion');
        }

        return ucfirst($this->cvScope);
    }
}
