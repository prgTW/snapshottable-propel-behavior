<?php

namespace prgTW\SnapshottableBehavior;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;

class SnapshottableBehaviorObjectBuilderModifier
{
    /** @var SnapshottableBehavior */
    protected $behavior;

    /** @var Table */
    private $table;

    /**
     * @param SnapshottableBehavior $behavior
     */
    public function __construct(SnapshottableBehavior $behavior)
    {
        $this->behavior = $behavior;
        $this->table = $behavior->getTable();
    }

    /**
     * @param ObjectBuilder $builder
     *
     * @return string
     */
    public function objectAttributes(ObjectBuilder $builder)
    {
        if (!$this->behavior->hasSnapshotClass()) {
            $snapshotTable = $this->behavior->getSnapshotTable();
            $stubQueryBuilder = $builder->getNewStubQueryBuilder($snapshotTable);
            $builder->declareClassFromBuilder($stubQueryBuilder);
        }

        $script = '';

        return $script;
    }

    /**
     * @param ObjectBuilder $builder
     *
     * @return string
     */
    public function objectMethods(ObjectBuilder $builder)
    {
        if ($this->behavior->hasSnapshotClass()) {
            $snapshotClass = $this->behavior->getParameter(SnapshottableBehavior::PARAMETER_SNAPSHOT_CLASS);
            $builder->declareClass($snapshotClass);
        } else {
            $snapshotTable = $this->behavior->getSnapshotTable();
            $stubObjectBuilder = $builder->getNewStubObjectBuilder($snapshotTable);
            $builder->declareClassFromBuilder($stubObjectBuilder);
        }

        $script = '';
        $script .= $this->addSnapshot($builder);

        return $script;
    }

    /**
     * @param ObjectBuilder $builder
     *
     * @return string
     */
    public function addSnapshot(ObjectBuilder $builder)
    {
        $referenceColumnName = $this->behavior->getParameter(SnapshottableBehavior::PARAMETER_REFERENCE_COLUMN);

        $indexName = $this->behavior->getParameter(SnapshottableBehavior::PARAMETER_SNAPSHOT_UNIQUE_COLUMNS_INDEX_NAME);
        $snapshotTable = $this->behavior->getSnapshotTable();
        $uniqueIndices = array_filter(
            $this->behavior->getSnapshotTable()->getUnices(),
            function (Unique $i) use ($indexName) {
                return $indexName === $i->getName();
            }
        );
        /** @var Unique $uniqueIndex */
        $uniqueIndex = reset($uniqueIndices);
        $referenceColumn = $this->behavior->getSnapshotTable()->getColumn($referenceColumnName);
        $vars = [
            'primaryKeyColumnPhpName' => $this->behavior->getTable()->getFirstPrimaryKeyColumn()->getPhpName(),
            'snapshotTablePhpName' => $this->behavior->getSnapshotTablePhpName($builder),
            'referenceColumnPhpName' => $referenceColumn->getPhpName(),
            'primaryKeyColumn' => $this->behavior->getTable()->getFirstPrimaryKeyColumn(),
            'snapshotAtColumn' => $this->behavior->getSnapshotAtColumn(),
            'hasSnapshotClass' => $this->behavior->hasSnapshotClass(),
            'queryClassName' => $builder->getClassNameFromBuilder($builder->getNewStubQueryBuilder($snapshotTable)),
            'uniqueColumns' => array_combine(
                $uniqueIndex->getColumns(),
                array_map(
                    function ($name) {
                        return $this->behavior->getSnapshotTable()->getColumn($name)->getPhpName();
                    },
                    $uniqueIndex->getColumns()
                )
            ),
        ];

        return $this->behavior->renderTemplate('objectSnapshot', $vars);
    }
}
