<?php

namespace prgTW\SnapshottableBehavior\Tests;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\Exception\PropelException;
use SnapshottableTest\Product;
use SnapshottableTest\ProductSnapshotQuery;

class SnapshottableBehaviorTest extends TestCase
{
    const SCHEMA = <<<'XML'
<database name="snapshottable_test" namespace="SnapshottableTest" defaultIdMethod="native">
    <table name="product">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true" required="true"/>
        <column name="name" type="VARCHAR" size="100" description="Product name"/>
        <column name="price" type="DECIMAL" size="10" scale="2"/>
        <column name="stock" type="INTEGER"/>
        <behavior name="prgTW\SnapshottableBehavior\SnapshottableBehavior">
            <parameter name="snapshot_unique_columns" value="name,price"/>
            <parameter name="create_index_on_snapshot_column" value="true"/>
        </behavior>
    </table>
</database>
XML;

    protected function setUp(): void
    {
        // Propel itself still triggers deprecations on recent PHP; only those raised by this package fail the test.
        $packageDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        set_error_handler(function ($severity, $message, $file, $line) use ($packageDir) {
            if (0 !== strpos($file, $packageDir) || 0 === strpos($file, $packageDir . 'vendor' . DIRECTORY_SEPARATOR))
            {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        if (!class_exists(Product::class))
        {
            $builder = new QuickBuilder();
            $builder->setSchema(self::SCHEMA);
            $builder->setPlatform(new SqlitePlatform());
            $builder->setVfs(false);
            $builder->build();
        }
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testSnapshotMethodDeclaresNullableConnection()
    {
        $builder = new QuickBuilder();
        $builder->setSchema(self::SCHEMA);

        $this->assertStringContainsString('function snapshot(?ConnectionInterface $con = null)', $builder->getClasses());
    }

    public function testSnapshotTableCopiesColumns()
    {
        $snapshotTable = $this->buildDatabase(self::SCHEMA)->getTable('product_snapshot');

        $this->assertNotNull($snapshotTable);
        $this->assertSame(['id'], array_map(function ($column) {
            return $column->getName();
        }, $snapshotTable->getPrimaryKey()));
        $this->assertSame('Product name', $snapshotTable->getColumn('name')->getDescription());
        $this->assertFalse($snapshotTable->getColumn('name')->isNotNull());
        $this->assertSame(10, (int)$snapshotTable->getColumn('price')->getSize());
        $this->assertSame(2, (int)$snapshotTable->getColumn('price')->getScale());
        $this->assertTrue($snapshotTable->hasColumn('stock'));
        $this->assertTrue($snapshotTable->hasColumn('foreign_id'));
        $this->assertTrue($snapshotTable->hasColumn('snapshot_at'));
        $this->assertCount(1, $snapshotTable->getForeignKeys());
        $this->assertSame('product', $snapshotTable->getForeignKeys()[0]->getForeignTableName());
    }

    public function testSnapshotTableIndices()
    {
        $snapshotTable = $this->buildDatabase(self::SCHEMA)->getTable('product_snapshot');

        $indices = [];
        foreach ($snapshotTable->getIndices() as $index)
        {
            $indices[$index->getName()] = $index->getColumns();
        }

        $this->assertSame(['foreign_id'], $indices['foreign_id']);
        $this->assertSame(['foreign_id', 'name', 'price'], $indices['unique_columns_from_definition']);
        $this->assertSame(['snapshot_at'], $indices['snapshot_at']);
    }

    public function testDatabaseBehaviorSkipsSnapshotTables()
    {
        $database = $this->buildDatabase(<<<'XML'
<database name="snapshottable_database_test" namespace="SnapshottableDatabaseTest">
    <behavior name="prgTW\SnapshottableBehavior\SnapshottableBehavior"/>
    <table name="author">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true" required="true"/>
        <column name="name" type="VARCHAR" size="100"/>
    </table>
    <table name="book">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true" required="true"/>
        <column name="title" type="VARCHAR" size="100"/>
    </table>
</database>
XML
        );

        $tableNames = array_map(function ($table) {
            return $table->getName();
        }, $database->getTables());
        sort($tableNames);

        $this->assertSame(['author', 'author_snapshot', 'book', 'book_snapshot'], $tableNames);
    }

    public function testSnapshotOfNewObjectThrows()
    {
        $this->expectException(PropelException::class);

        (new Product())->snapshot();
    }

    public function testSnapshotIsReusedUntilUniqueColumnsChange()
    {
        $product = new Product();
        $product->setName('Chair');
        $product->setPrice('10.50');
        $product->setStock(3);
        $product->save();

        $first = $product->snapshot();
        $this->assertFalse($first->isNew());
        $this->assertSame($product->getId(), (int)$first->getForeignId());
        $this->assertSame('Chair', $first->getName());
        $this->assertNotNull($first->getSnapshotAt());

        $product->setStock(5);
        $product->save();
        $this->assertSame($first->getId(), $product->snapshot()->getId());

        $product->setPrice('12.00');
        $product->save();
        $second = $product->snapshot();
        $this->assertNotSame($first->getId(), $second->getId());

        $this->assertSame(2, ProductSnapshotQuery::create()->filterByForeignId($product->getId())->count());
    }

    private function buildDatabase($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchema($schema);

        return $builder->getDatabase();
    }
}
