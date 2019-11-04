<?php

declare(strict_types=1);

namespace Larvatatw\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\PumpStream;
use PHPUnit\Framework\TestCase;

class PumpStreamTest extends TestCase
{
    public function testHasMetadataAndSize()
    {
        $p = new PumpStream(function () {}, [
            'metadata' => ['foo' => 'bar'],
            'size'     => 100
        ]);

        $this->assertEquals('bar', $p->getMetadata('foo'));
        $this->assertEquals(['foo' => 'bar'], $p->getMetadata());
        $this->assertEquals(100, $p->getSize());
    }

    public function testCanReadFromCallable()
    {
        $p = Psr7\stream_for(function ($size) {
            return 'a';
        });
        $this->assertEquals('a', $p->read(1));
        $this->assertEquals(1, $p->tell());
        $this->assertEquals('aaaaa', $p->read(5));
        $this->assertEquals(6, $p->tell());
    }

    public function testStoresExcessDataInBuffer()
    {
        $called = [];
        $p = Psr7\stream_for(function ($size) use (&$called) {
            $called[] = $size;
            return 'abcdef';
        });
        $this->assertEquals('a', $p->read(1));
        $this->assertEquals('b', $p->read(1));
        $this->assertEquals('cdef', $p->read(4));
        $this->assertEquals('abcdefabc', $p->read(9));
        $this->assertEquals([1, 9, 3], $called);
    }

    public function testInifiniteStreamWrappedInLimitStream()
    {
        $p = Psr7\stream_for(function () { return 'a'; });
        $s = new LimitStream($p, 5);
        $this->assertEquals('aaaaa', (string) $s);
    }

    public function testDescribesCapabilities()
    {
        $p = Psr7\stream_for(function () {});
        $this->assertTrue($p->isReadable());
        $this->assertFalse($p->isSeekable());
        $this->assertFalse($p->isWritable());
        $this->assertNull($p->getSize());
        $this->assertEquals('', $p->getContents());
        $this->assertEquals('', (string) $p);
        $p->close();
        $this->assertEquals('', $p->read(10));
        $this->assertTrue($p->eof());

        try {
            $this->assertFalse($p->write('aa'));
            $this->fail();
        } catch (\RuntimeException $e) {}
    }

    public function testThatConvertingStreamToStringWillTriggerErrorAndWillReturnEmptyString()
    {
        $p = Psr7\stream_for(function ($size) {
            throw new \Exception();
        });
        $this->assertInstanceOf(PumpStream::class, $p);

        $errors = [];
        set_error_handler(function (int $errorNumber, string $errorMessage) use (&$errors){
            $errors[] = ['number' => $errorNumber, 'message' => $errorMessage];
        });
        (string) $p;

        restore_error_handler();

        $this->assertCount(1, $errors);
        $this->assertSame(E_USER_ERROR, $errors[0]['number']);
        $this->assertStringStartsWith('GuzzleHttp\Psr7\PumpStream::__toString exception:', $errors[0]['message']);
    }
}
