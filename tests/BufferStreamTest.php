<?php

declare(strict_types=1);

namespace Larvatatw\Tests\Psr7;

use GuzzleHttp\Psr7\BufferStream;
use PHPUnit\Framework\TestCase;

class BufferStreamTest extends TestCase
{
    public function testHasMetadata()
    {
        $b = new BufferStream(10);
        $this->assertTrue($b->isReadable());
        $this->assertTrue($b->isWritable());
        $this->assertFalse($b->isSeekable());
        $this->assertEquals(null, $b->getMetadata('foo'));
        $this->assertEquals(10, $b->getMetadata('hwm'));
        $this->assertEquals([], $b->getMetadata());
    }

    public function testRemovesReadDataFromBuffer()
    {
        $b = new BufferStream();
        $this->assertEquals(3, $b->write('foo'));
        $this->assertEquals(3, $b->getSize());
        $this->assertFalse($b->eof());
        $this->assertEquals('foo', $b->read(10));
        $this->assertTrue($b->eof());
        $this->assertEquals('', $b->read(10));
    }

    public function testCanCastToStringOrGetContents()
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->write('baz');
        $this->assertEquals('foo', $b->read(3));
        $b->write('bar');
        $this->assertEquals('bazbar', (string) $b);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine the position of a BufferStream');
        $b->tell();
    }

    public function testDetachClearsBuffer()
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->detach();
        $this->assertTrue($b->eof());
        $this->assertEquals(3, $b->write('abc'));
        $this->assertEquals('abc', $b->read(10));
    }

    public function testExceedingHighwaterMarkReturnsFalseButStillBuffers()
    {
        $b = new BufferStream(5);
        $this->assertEquals(3, $b->write('hi '));
        $this->assertFalse($b->write('hello'));
        $this->assertEquals('hi hello', (string) $b);
        $this->assertEquals(4, $b->write('test'));
    }
}
