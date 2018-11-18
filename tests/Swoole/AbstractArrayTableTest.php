<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;

use Swoole\Table;
use Swoole\Lock;

use ResizeServer\Swoole\AbstractArrayTable;

class AbstractArrayTableTest extends TestCase
{
    public function setUp()
    {
        $table = new Table(1);
        $table->column('string', Table::TYPE_STRING, 16);
        $table->create();

        /**
         * @var \Swoole\Table
         */
        $this->table = $table;

        /**
         * @var \ResizeServer\Swoole\AbstractArrayTable
         */
        $this->arrayTable = new class($this->table) extends AbstractArrayTable {
            public static function buildTable(): Table
            {
                return new Table(1);
            }
            public function setString(string $id, string $data): void
            {
                $this->table->set($id, ['string' => $data]);
            }
            public function getString(string $id): ?string
            {
                if ($result = $this->table->get($id, 'string')) {
                    return $result;
                }
                return null;
            }
        };
    }

    public function tearDown()
    {
        $this->table->destroy();
    }

    private function fillTable(int $count): int
    {
        for ($i = 0; $i < $count; $i++) {
            $this->table->set("item" . $i, ['string' => 'value' . $i]);
        }
        return $this->table->count();
    }

    public function testRotateTableIfNotFull()
    {
        $start = $this->fillTable(AbstractArrayTable::TABLE_MAX_COUNT - 1);
        $this->assertNull($this->arrayTable->rotateTable($this->table));
    }

    public function testRotateTableIfFull()
    {
        $start = $this->fillTable(AbstractArrayTable::TABLE_MAX_COUNT);
        $this->assertEquals(
            AbstractArrayTable::TABLE_MAX_COUNT - AbstractArrayTable::BATCH_SIZE,
            $this->arrayTable->rotateTable($this->table)
        );
    }

    public function testRemove()
    {
        $key = "deleteme";
        $this->arrayTable->setString($key, $key);
        $this->assertSame(
            $key,
            $this->arrayTable->getString($key)
        );
        $this->arrayTable->remove($key);
        $this->assertNull($this->arrayTable->getString($key));
    }
}
