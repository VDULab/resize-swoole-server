<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;

use ResizeServer\WebSocket\Message\ImageUrls;
use ResizeServer\WebSocket\Message\PathEntry;

class ImageUrlsTest extends TestCase
{
    public function setUp()
    {
        $this->path = $this->prophesize(PathEntry::class);
    }

    public function testConstructor()
    {
        $message = new ImageUrls(['a', 'b', 'c']);
        $this->assertInstanceOf(ImageUrls::class, $message);
    }

    public function testBuildFromScanResults()
    {
        $firstPath = new PathEntry('/test/');
        $results = [
            $firstPath,
            $this->path->reveal(),
        ];

        /**
         * @var \ResizeServer\WebSocket\Message\ImageUrls
         */
        $message = ImageUrls::buildFromScanResults($results);
        $this->assertEquals(2, count($message->images));
        $this->assertArraySubset(['/test/'], $message->images);
    }
}
