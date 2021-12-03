<?php

namespace prgTW\SnapshottableBehavior;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Model\Behavior;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Domain;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;

class SnapshottableBehavior extends Behavior
{
    const PARAMETER_SNAPSHOT_UNIQUE_COLUMNS            = 'snapshot_unique_columns';
    const PARAMETER_SNAPSHOT_UNIQUE_COLUMNS_INDEX_NAME = 'snapshot_unique_columns_index_name';
    const PARAMETER_SNAPSHOT_CLASS                     = 'snapshot_class';
    const PARAMETER_SNAPSHOT_TABLE                     = 'snapshot_table';
    const PARAMETER_SNAPSHOT_PHPNAME                   = 'snapshot_phpname';
    const PARAMETER_REFERENCE_COLUMN                   = 'reference_column';
    const PARAMETER_SNAPSHOT_AT_COLUMN                 = 'snapshot_at_column';
    const PARAMETER_SNAPSHOT_AT_COLUMN_INDEX_NAME      = 'snapshot_at_column_index_name';
    const PARAMETER_LOG_SNAPSHOT_AT                    = 'log_snapshot_at';
    const PARAMETER_CREATE_INDEX_ON_SNAPSHOT_COLUMN    = 'create_index_on_snapshot_column';

    /** @var array */
    protected $parameters = [
        self::PARAMETER_SNAPSHOT_UNIQUE_COLUMNS            => '',
        self::PARAMETER_SNAPSHOT_UNIQUE_COLUMNS_INDEX_NAME => 'unique_columns_from_definition',
        self::PARAMETER_SNAPSHOT_TABLE                     => '',
        self::PARAMETER_SNAPSHOT_PHPNAME                   => null,
        self::PARAMETER_SNAPSHOT_CLASS                     => '',
        self::PARAMETER_LOG_SNAPSHOT_AT                    => 'true',
        self::PARAMETER_REFERENCE_COLUMN                   => 'foreign_id',
        self::PARAMETER_SNAPSHOT_AT_COLUMN                 => 'snapshot_at',
        self::PARAMETER_SNAPSHOT_AT_COLUMN_INDEX_NAME      => 'snapshot_at',
        self::PARAMETER_CREATE_INDEX_ON_SNAPSHOT_COLUMN    => 'false',
    ];

    /** @var Table */
    private $snapshotTable;

    /** @var SnapshottableBehaviorObjectBuilderModifier */
    private $objectBuilderModifier;

    /** {@inheritdoc} */
    public function modifyDatabase()
    {
        $database = $this->getDatabase();
        $tables   = $database->getTables();
        foreach ($tables as $table)
        {
            if ($table->hasBehavior($this->getId()))
            {
                continue;
            }

            if (property_exists($table, 'isSnapshotTable'))
            {
                continue;
            }
            $b = clone $this;
            $table->addBehavior($b);
        }
    }

    /** {@inheritdoc} */
    public function modifyTable()
    {
        if ($this->getParameter(self::PARAMETER_SNAPSHOT_CLASS) && $this->getParameter(
                self::PARAMETER_SNAPSHOT_TABLE
            ))
        {
            throw new \InvalidArgumentException(
                vsprintf(
                    'Please set only one of the two parameters "%s" and "%s".',
                    [
                        self::PARAMETER_SNAPSHOT_CLASS,
                        self::PARAMETER_SNAPSHOT_TABLE,
                    ]
                )
            );
        }
        if (!$this->getParameter(self::PARAMETER_SNAPSHOT_CLASS))
        {
            $this->addSnapshotTable();
        }
    }

    private function addSnapshotTable()
    {
        $table             = $this->getTable();
        $primaryKeyColumn  = $table->getFirstPrimaryKeyColumn();
        $database          = $table->getDatabase();
        $snapshotTableName = $this->getParameter(self::PARAMETER_SNAPSHOT_TABLE) ?: $this->getDefaultSnapshotTableName();

        if ($database->hasTable($snapshotTableName))
        {
            $this->snapshotTable                  = $database->getTable($snapshotTableName);
            $this->snapshotTable->isSnapshotTable = true;

            return;
        }

        $snapshotTable = $database->addTable(
            [
                'name'      => $snapshotTableName,
                'phpName'   => $this->getParameter(self::PARAMETER_SNAPSHOT_PHPNAME),
                'package'   => $table->getPackage(),
                'schema'    => $table->getSchema(),
                'namespace' => $table->getNamespace() ? '\\' . $table->getNamespace() : null,
            ]
        );

        $addSnapshotAt            = true;
        $hasTimestampableBehavior = 0 < count(
                array_filter(
                    $database->getBehaviors(),
                    function (Behavior $behavior) {
                        return 'timestampable' === $behavior->getName();
                    }
                )
            );
        if ($hasTimestampableBehavior)
        {
            $addSnapshotAt         = false;
            $timestampableBehavior = clone $database->getBehavior('timestampable');
            $timestampableBehavior->setParameters(
                array_merge(
                    $timestampableBehavior->getParameters(),
                    [
                        'create_column'      => $this->getParameter(self::PARAMETER_SNAPSHOT_AT_COLUMN),
                        'disable_updated_at' => 'true',
                    ]
                )
            );
            $snapshotTable->addBehavior($timestampableBehavior);
        }
        $snapshotTable->isSnapshotTable = true;

        $idColumn = $snapshotTable->addColumn(
            [
                'name' => 'id',
                'type' => 'INTEGER',
            ]
        );
        $idColumn->setAutoIncrement(true);
        $idColumn->setPrimaryKey(true);
        $idColumn->setNotNull(true);

        $columns = $table->getColumns();
        foreach ($columns as $column)
        {
            if ($column->isPrimaryKey())
            {
                continue;
            }

            $domain = $column->getDomain();

            $newColumnDomain = new Domain;
            $newColumnDomain->setDescription($domain->getDescription());
            $newColumnDomain->setName($domain->getName());
            $newColumnDomain->setScale($domain->getScale());
            $newColumnDomain->setSize($domain->getSize());
            $newColumnDomain->setSqlType($domain->getSqlType());
            $newColumnDomain->setType($domain->getType());
            foreach ($domain->getVendorInformation() as $vendorInfo)
            {
                $newColumnDomain->addVendorInfo($vendorInfo);
            }

            $columnInSnapshotTable = new Column;
            $columnInSnapshotTable->setDomain($newColumnDomain);
            $columnInSnapshotTable->setName($column->getName());
            $columnInSnapshotTable->setNotNull(false);
            $columnInSnapshotTable->setDescription($column->getDescription());
            $columnInSnapshotTable->setType($column->getType());
            $columnInSnapshotTable->setTypeHint($column->getTypeHint());
            $columnInSnapshotTable->setPhpName($column->getPhpName());
            $columnInSnapshotTable->setPhpSingularName($column->getPhpSingularName());
            $columnInSnapshotTable->setScale($column->getScale());
            $columnInSnapshotTable->setSize($column->getSize());
            $columnInSnapshotTable->setValueSet($column->getValueSet());
            foreach ($column->getVendorInformation() as $vendorInfo)
            {
                $columnInSnapshotTable->addVendorInfo($vendorInfo);
            }
            $snapshotTable->addColumn($columnInSnapshotTable);
        }

        $foreignKeyColumn = $snapshotTable->addColumn(
            [
                'name' => $this->getParameter(self::PARAMETER_REFERENCE_COLUMN),
                'type' => $primaryKeyColumn->getType(),
                'size' => $primaryKeyColumn->getSize(),
            ]
        );

        $index = new Index;
        $index->setName($this->getParameter(self::PARAMETER_REFERENCE_COLUMN));
        if ($primaryKeyColumn->getSize())
        {
            $index->addColumn(
                [
                    'name' => $this->getParameter(self::PARAMETER_REFERENCE_COLUMN),
                    'size' => $primaryKeyColumn->getSize(),
                ]
            );
        }
        else
        {
            $index->addColumn(
                [
                    'name' => $this->getParameter(self::PARAMETER_REFERENCE_COLUMN),
                ]
            );
        }
        $snapshotTable->addIndex($index);

        $foreignKey = new ForeignKey;
        $foreignKey->setName(
            vsprintf(
                'fk_%s_%s',
                [
                    $snapshotTable->getOriginCommonName(),
                    $this->getParameter(self::PARAMETER_REFERENCE_COLUMN),
                ]
            )
        );
        $foreignKey->setOnUpdate('CASCADE');
        $foreignKey->setOnDelete('RESTRICT');
        $foreignKey->setForeignTableCommonName($this->getTable()->getCommonName());
        $foreignKey->addReference($foreignKeyColumn, $primaryKeyColumn);
        $snapshotTable->addForeignKey($foreignKey);

        if ($this->getParameter(self::PARAMETER_LOG_SNAPSHOT_AT) == 'true' && $addSnapshotAt)
        {
            $snapshotTable->addColumn(
                [
                    'name' => $this->getParameter(self::PARAMETER_SNAPSHOT_AT_COLUMN),
                    'type' => 'TIMESTAMP',
                ]
            );
        }

        $indices = $table->getIndices();
        foreach ($indices as $index)
        {
            $copiedIndex = clone $index;
            $snapshotTable->addIndex($copiedIndex);
        }

        // copy unique indices to indices
        // see https://github.com/propelorm/Propel/issues/175 for details
        $unices = $table->getUnices();
        foreach ($unices as $unique)
        {
            $index = new Index;
            $index->setName($unique->getName());
            $columns = $unique->getColumns();
            foreach ($columns as $columnName)
            {
                if ($size = $unique->getColumnSize($columnName))
                {
                    $index->addColumn(['name' => $columnName, 'size' => $size]);
                }
                else
                {
                    $index->addColumn(['name' => $columnName]);
                }
            }
            $snapshotTable->addIndex($index);
        }

        $behaviors = $database->getBehaviors();
        foreach ($behaviors as $behavior)
        {
            $behavior->modifyDatabase();
        }

        $indexForUniqueColumns = new Index;
        $indexForUniqueColumns->setName($this->getParameter(self::PARAMETER_SNAPSHOT_UNIQUE_COLUMNS_INDEX_NAME));
        $indexForUniqueColumns->addColumn(['name' => $this->getParameter(self::PARAMETER_REFERENCE_COLUMN)]);
        foreach ($this->getUniqueColumns() as $uniqueColumnDef)
        {
            if (null === $uniqueColumnDef['indexSize'])
            {
                $indexForUniqueColumns->addColumn(['name' => $uniqueColumnDef['name']]);
            }
            elseif ($uniqueColumnDef['indexSize'] > 0)
            {
                $indexForUniqueColumns->addColumn(
                    [
                        'name' => $uniqueColumnDef['name'],
                        'size' => $uniqueColumnDef['indexSize'],
                    ]
                );
            }
        }
        $snapshotTable->addIndex($indexForUniqueColumns);

        if ('true' == $this->getParameter(self::PARAMETER_CREATE_INDEX_ON_SNAPSHOT_COLUMN))
        {
            $indexForSnapshotAtColumn = new Index;
            $indexForSnapshotAtColumn->setName($this->getParameter(self::PARAMETER_SNAPSHOT_AT_COLUMN_INDEX_NAME));
            $indexForSnapshotAtColumn->addColumn(
                [
                    'name' => $this->getParameter(self::PARAMETER_SNAPSHOT_AT_COLUMN)
                ]
            );

            $snapshotTable->addIndex($indexForSnapshotAtColumn);
        }

        $this->snapshotTable = $snapshotTable;
    }

    /**
     * @return Table
     */
    public function getSnapshotTable()
    {
        return $this->snapshotTable;
    }

    /**
     * @param ObjectBuilder $builder
     *
     * @return array
     */
    public function getSnapshotTablePhpName(ObjectBuilder $builder)
    {
        if ($this->hasSnapshotClass())
        {
            return $this->getParameter(self::PARAMETER_SNAPSHOT_CLASS);
        }

        return $builder->getNewStubObjectBuilder($this->snapshotTable)->getClassname();
    }

    /**
     * @return bool
     */
    public function hasSnapshotClass()
    {
        return $this->getParameter(self::PARAMETER_SNAPSHOT_CLASS) != '';
    }

    /**
     * @return Column
     */
    public function getSnapshotAtColumn()
    {
        if ($this->getSnapshotTable() && $this->getParameter(self::PARAMETER_LOG_SNAPSHOT_AT) == 'true')
        {
            $snapshotAtColumn = $this->getParameter(self::PARAMETER_SNAPSHOT_AT_COLUMN);

            return $this->getSnapshotTable()->getColumn($snapshotAtColumn);
        }

        return null;
    }

    /**
     * @return SnapshottableBehaviorObjectBuilderModifier
     */
    public function getObjectBuilderModifier()
    {
        if (null === $this->objectBuilderModifier)
        {
            $this->objectBuilderModifier = new SnapshottableBehaviorObjectBuilderModifier($this);
        }

        return $this->objectBuilderModifier;
    }

    /**
     * @return array
     */
    public function getUniqueColumns()
    {
        $uniqueColumns = explode(',', $this->getParameter(self::PARAMETER_SNAPSHOT_UNIQUE_COLUMNS));
        $uniqueColumns = array_filter(array_map('trim', $uniqueColumns));
        $uniqueColumns = array_map(
            function ($definition) {
                if (!preg_match('/(?P<columnName>\w+)(?:\((?P<indexSize>\d+)\))?$/uis', $definition, $matches))
                {
                    throw new \LogicException('');
                }

                return [
                    'name'      => $matches['columnName'],
                    'indexSize' => isset($matches['indexSize']) ? $matches['indexSize'] : null,
                ];
            },
            $uniqueColumns
        );

        return $uniqueColumns;
    }

    /**
     * @return string
     */
    private function getDefaultSnapshotTableName()
    {
        return $this->getTable()->getName() . '_snapshot';
    }
}
