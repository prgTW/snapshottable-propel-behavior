/**
 * Copy the data of the current object into a <?php echo $snapshotTablePhpName ?> snapshot object.
 * The snapshot object is then saved.
 *
 * @param ConnectionInterface $con Optional connection object
 *
 * @throws PropelException If the object is new
 *
 * @return <?php echo $snapshotTablePhpName ?> The object based on this object
 */
public function snapshot(ConnectionInterface $con = null)
{
	if ($this->isNew()) {
		throw new PropelException('New objects cannot be snapshoted. You must save the current object before calling snapshot().');
	}

	$snapshot = (new <?php echo $queryClassName; ?>)
		->filterBy<?php echo $referenceColumnPhpName; ?>($this->get<?php echo $primaryKeyColumnPhpName; ?>())
<?php foreach ($uniqueColumns as $columnPhpName): ?>
		->filterBy<?php echo $columnPhpName; ?>($this->get<?php echo $columnPhpName; ?>())
<?php endforeach; ?>
		->findOneOrCreate();

<?php if ($snapshotAtColumn): ?>
	if ($snapshot->isNew()) {
		$snapshot->set<?php echo $snapshotAtColumn->getPhpName() ?>(time());
		$snapshot->save(<?php if(!$hasSnapshotClass): ?>$con<?php endif; ?>);
	}
<?php endif; ?>

	return $snapshot;
}
