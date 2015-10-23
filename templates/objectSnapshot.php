/**
 * Copy the data of the current object into a $snapshotTablePhpName snapshot object.
 * The snapshot object is then saved.
 *
 * @param PropelPDO $con Optional connection object
 *
 * @throws PropelException If the object is new
 *
 * @return <?php echo $snapshotTablePhpName ?> The object based on this object
 */
public function snapshot(PropelPDO $con = null)
{
	if ($this->isNew()) {
		throw new PropelException('New objects cannot be snapshoted. You must save the current object before calling snapshot().');
	}

	$snapshot = new <?php echo $snapshotTablePhpName ?>;
	$snapshot->set<?php echo $referenceColumn->getPhpName() ?>($this->get<?php echo $primaryKeyColumn->getPhpName() ?>());
	$this->copyInto($snapshot, $deepCopy = false, $makeNew = false);
<?php if ($snapshotAtColumn): ?>
	$snapshot->set<?php echo $snapshotAtColumn->getPhpName() ?>(time());
<?php endif; ?>
	$snapshot->save(<?php if(!$hasSnapshotClass): ?>$con<?php endif; ?>);

	return $snapshot;
}
