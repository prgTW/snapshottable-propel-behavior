<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Keeps tracks of an ActiveRecord object, even after deletion
 *
 * @author     François Zaninotto
 * @package    propel.generator.behavior.archivable
 */
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
	 * Add object attributes to the built class.
	 *
	 * @param PHP5ObjectBuilder $builder
	 *
	 * @return string The PHP code to be added to the builder.
	 */
	public function objectAttributes(PHP5ObjectBuilder $builder)
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
	 * @return string the PHP code to be added to the builder
	 */
	public function objectMethods($builder)
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
	 * @return string the PHP code to be added to the builder
	 */
	public function addSnapshot($builder)
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
