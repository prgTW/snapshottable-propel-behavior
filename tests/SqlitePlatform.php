<?php

namespace prgTW\SnapshottableBehavior\Tests;

use Propel\Generator\Model\Index;
use Propel\Generator\Platform\SqlitePlatform as BaseSqlitePlatform;

/**
 * The behaviour defines snapshot indices by column name only. MySQL renders them from the names,
 * the generic platforms from column objects, so the test database renders them from the names too.
 */
class SqlitePlatform extends BaseSqlitePlatform
{
    public function getAddIndexDDL(Index $index): string
    {
        return sprintf(
            "\nCREATE %sINDEX %s ON %s (%s);\n",
            $index->isUnique() ? 'UNIQUE ' : '',
            $this->quoteIdentifier($index->getName()),
            $this->quoteIdentifier($index->getTable()->getName()),
            implode(',', array_map([$this, 'quoteIdentifier'], $index->getColumns()))
        );
    }
}
