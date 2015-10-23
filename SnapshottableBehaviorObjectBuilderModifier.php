<?php

namespace prgTW\SnapshottableBehavior;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Model\Table;

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
		$this->table    = $behavior->getTable();
	}

	/**
	 * @param ObjectBuilder $builder
	 *
	 * @return string
	 */
	public function objectAttributes(ObjectBuilder $builder)
	{
		if (!$this->behavior->hasSnapshotClass())
		{
			$snapshotTable    = $this->behavior->getSnapshotTable();
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
		if ($this->behavior->hasSnapshotClass())
		{
			$snapshotClass = $this->behavior->getParameter(SnapshottableBehavior::PARAMETER_SNAPSHOT_CLASS);
			$builder->declareClass($snapshotClass);
		}
		else
		{
			$snapshotTable     = $this->behavior->getSnapshotTable();
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

		return $this->behavior->renderTemplate('objectSnapshot', [
			'snapshotTablePhpName' => $this->behavior->getSnapshotTablePhpName($builder),
			'referenceColumn'      => $this->behavior->getSnapshotTable()->getColumn($referenceColumnName),
			'primaryKeyColumn'     => $this->behavior->getTable()->getFirstPrimaryKeyColumn(),
			'snapshotAtColumn'     => $this->behavior->getSnapshotAtColumn(),
			'hasSnapshotClass'     => $this->behavior->hasSnapshotClass(),
		]);
	}
}
