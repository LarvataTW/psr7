<?php

declare(strict_types=1);

namespace Larvatatw\Tests\Psr7;

use GuzzleHttp\Psr7\AppendStream;
use GuzzleHttp\Psr7;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class AppendStreamTest extends TestCase
{
    public function testValidatesStreamsAreReadable()
    {
        $a = new AppendStream();
        $s = $this->getMockBuilder(StreamInterface::class)
            ->setMethods(['isReadable'])
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(false));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each stream must be readable');
        $a->addStream($s);
    }

    public function testValidatesSeekType()
    {
        $a = new AppendStream();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The AppendStream can only seek with SEEK_SET');
        $a->seek(100, SEEK_CUR);
    }

    public function testTriesToRewindOnSeek()
    {
        $a = new AppendStream();
        $s = $this->getMockBuilder(StreamInterface::class)
            ->setMethods(['isReadable', 'rewind', 'isSeekable'])
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));
        $s->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(true));
        $s->expects($this->once())
            ->method('rewind')
            ->will($this->throwException(new \RuntimeException()));
        $a->addStream($s);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to seek stream 0 of the AppendStream');
        $a->seek(10);
    }

    public function testSeeksToPositionByReading()
    {
        $a = new AppendStream([
            Psr7\stream_for('foo'),
            Psr7\stream_for('bar'),
            Psr7\stream_for('baz'),
        ]);

        $a->seek(3);
        $this->assertEquals(3, $a->tell());
        $this->assertEquals('bar', $a->read(3));

        $a->seek(6);
        $this->assertEquals(6, $a->tell());
        $this->assertEquals('baz', $a->read(3));
    }

    public function testDetachWithoutStreams()
    {
        $s = new AppendStream();
        $s->detach();

        $this->assertSame(0, $s->getSize());
        $this->assertTrue($s->eof());
        $this->assertTrue($s->isReadable());
        $this->assertSame('', (string) $s);
        $this->assertTrue($s->isSeekable());
        $this->assertFalse($s->isWritable());
    }

    public function testDetachesEachStream()
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\stream_for($handle);
        $s2 = Psr7\stream_for('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->detach();

        $this->assertSame(0, $a->getSize());
        $this->assertTrue($a->eof());
        $this->assertTrue($a->isReadable());
        $this->assertSame('', (string) $a);
        $this->assertTrue($a->isSeekable());
        $this->assertFalse($a->isWritable());

        $this->assertNull($s1->detach());
        $this->assertIsResource($handle, 'resource is not closed when detaching');
        fclose($handle);
    }

    public function testClosesEachStream()
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\stream_for($handle);
        $s2 = Psr7\stream_for('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->close();

        $this->assertSame(0, $a->getSize());
        $this->assertTrue($a->eof());
        $this->assertTrue($a->isReadable());
        $this->assertSame('', (string) $a);
        $this->assertTrue($a->isSeekable());
        $this->assertFalse($a->isWritable());

        $this->assertFalse(is_resource($handle));
    }

    public function testIsNotWritable()
    {
        $a = new AppendStream([Psr7\stream_for('foo')]);
        $this->assertFalse($a->isWritable());
        $this->assertTrue($a->isSeekable());
        $this->assertTrue($a->isReadable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to an AppendStream');
        $a->write('foo');
    }

    public function testDoesNotNeedStreams()
    {
        $a = new AppendStream();
        $this->assertEquals('', (string) $a);
    }

    public function testCanReadFromMultipleStreams()
    {
        $a = new AppendStream([
            Psr7\stream_for('foo'),
            Psr7\stream_for('bar'),
            Psr7\stream_for('baz'),
        ]);
        $this->assertFalse($a->eof());
        $this->assertSame(0, $a->tell());
        $this->assertEquals('foo', $a->read(3));
        $this->assertEquals('bar', $a->read(3));
        $this->assertEquals('baz', $a->read(3));
        $this->assertSame('', $a->read(1));
        $this->assertTrue($a->eof());
        $this->assertSame(9, $a->tell());
        $this->assertEquals('foobarbaz', (string) $a);
    }

    public function testCanDetermineSizeFromMultipleStreams()
    {
        $a = new AppendStream([
            Psr7\stream_for('foo'),
            Psr7\stream_for('bar')
        ]);
        $this->assertEquals(6, $a->getSize());

        $s = $this->getMockBuilder(StreamInterface::class)
            ->setMethods(['isSeekable', 'isReadable'])
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(null));
        $s->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));
        $a->addStream($s);
        $this->assertNull($a->getSize());
    }

    public function testCatchesExceptionsWhenCastingToString()
    {
        $s = $this->getMockBuilder(StreamInterface::class)
            ->setMethods(['isSeekable', 'read', 'isReadable', 'eof'])
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(true));
        $s->expects($this->once())
            ->method('read')
            ->will($this->throwException(new \RuntimeException('foo')));
        $s->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));
        $s->expects($this->any())
            ->method('eof')
            ->will($this->returnValue(false));
        $a = new AppendStream([$s]);
        $this->assertFalse($a->eof());

        $errors = [];
        set_error_handler(function (int $errorNumber, string $errorMessage) use (&$errors){
            $errors[] = ['number' => $errorNumber, 'message' => $errorMessage];
        });
        (string) $a;

        restore_error_handler();

        $this->assertCount(1, $errors);
        $this->assertSame(E_USER_ERROR, $errors[0]['number']);
        $this->assertStringStartsWith('GuzzleHttp\Psr7\AppendStream::__toString exception:', $errors[0]['message']);
    }

    public function testReturnsEmptyMetadata()
    {
        $s = new AppendStream();
        $this->assertEquals([], $s->getMetadata());
        $this->assertNull($s->getMetadata('foo'));
    }
}
