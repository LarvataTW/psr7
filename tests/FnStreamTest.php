<?php

declare(strict_types=1);

namespace Larvatatw\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers GuzzleHttp\Psr7\FnStream
 */
class FnStreamTest extends TestCase
{
    public function testThrowsWhenNotImplemented()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('seek() is not implemented in the FnStream');
        (new FnStream([]))->seek(1);
    }

    public function testProxiesToFunction()
    {
        $s = new FnStream([
            'read' => function ($len) {
                $this->assertEquals(3, $len);
                return 'foo';
            }
        ]);

        $this->assertEquals('foo', $s->read(3));
    }

    public function testCanCloseOnDestruct()
    {
        $called = false;
        $s = new FnStream([
            'close' => function () use (&$called) {
                $called = true;
            }
        ]);
        unset($s);
        $this->assertTrue($called);
    }

    public function testDoesNotRequireClose()
    {
        $s = new FnStream([]);
        unset($s);
        $this->assertTrue(true); // strict mode requires an assertion
    }

    public function testDecoratesStream()
    {
        $a = Psr7\stream_for('foo');
        $b = FnStream::decorate($a, []);
        $this->assertEquals(3, $b->getSize());
        $this->assertEquals($b->isWritable(), true);
        $this->assertEquals($b->isReadable(), true);
        $this->assertEquals($b->isSeekable(), true);
        $this->assertEquals($b->read(3), 'foo');
        $this->assertEquals($b->tell(), 3);
        $this->assertEquals($a->tell(), 3);
        $this->assertSame('', $a->read(1));
        $this->assertEquals($b->eof(), true);
        $this->assertEquals($a->eof(), true);
        $b->seek(0);
        $this->assertEquals('foo', (string) $b);
        $b->seek(0);
        $this->assertEquals('foo', $b->getContents());
        $this->assertEquals($a->getMetadata(), $b->getMetadata());
        $b->seek(0, SEEK_END);
        $b->write('bar');
        $this->assertEquals('foobar', (string) $b);
        $this->assertIsResource($b->detach());
        $b->close();
    }

    public function testDecoratesWithCustomizations()
    {
        $called = false;
        $a = Psr7\stream_for('foo');
        $b = FnStream::decorate($a, [
            'read' => function ($len) use (&$called, $a) {
                $called = true;
                return $a->read($len);
            }
        ]);
        $this->assertEquals('foo', $b->read(3));
        $this->assertTrue($called);
    }

    public function testDoNotAllowUnserialization()
    {
        $a = new FnStream([]);
        $b = serialize($a);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('FnStream should never be unserialized');
        unserialize($b);
    }

    public function testThatConvertingStreamToStringWillTriggerErrorAndWillReturnEmptyString()
    {
        $a = new FnStream([
            '__toString' => function () {
                throw new \Exception();
            },
        ]);

        $errors = [];
        set_error_handler(function (int $errorNumber, string $errorMessage) use (&$errors){
            $errors[] = ['number' => $errorNumber, 'message' => $errorMessage];
        });
        (string) $a;

        restore_error_handler();

        $this->assertCount(1, $errors);
        $this->assertSame(E_USER_ERROR, $errors[0]['number']);
        $this->assertStringStartsWith('GuzzleHttp\Psr7\FnStream::__toString exception:', $errors[0]['message']);
    }
}
