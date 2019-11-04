<?php

declare(strict_types=1);

namespace Larvatatw\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\NoSeekStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers GuzzleHttp\Psr7\NoSeekStream
 * @covers GuzzleHttp\Psr7\StreamDecoratorTrait
 */
class NoSeekStreamTest extends TestCase
{
    public function testCannotSeek()
    {
        $s = $this->getMockBuilder(StreamInterface::class)
            ->setMethods(['isSeekable', 'seek'])
            ->getMockForAbstractClass();
        $s->expects($this->never())->method('seek');
        $s->expects($this->never())->method('isSeekable');
        $wrapped = new NoSeekStream($s);
        $this->assertFalse($wrapped->isSeekable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot seek a NoSeekStream');
        $wrapped->seek(2);
    }

    public function testToStringDoesNotSeek()
    {
        $s = \GuzzleHttp\Psr7\stream_for('foo');
        $s->seek(1);
        $wrapped = new NoSeekStream($s);
        $this->assertEquals('oo', (string) $wrapped);

        $wrapped->close();
    }
}
