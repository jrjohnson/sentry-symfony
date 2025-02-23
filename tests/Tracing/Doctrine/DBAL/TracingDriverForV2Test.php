<?php

declare(strict_types=1);

namespace Sentry\SentryBundle\Tests\Tracing\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnectionInterface;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception\DriverException as DBALDriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use PHPUnit\Framework\MockObject\MockObject;
use Sentry\SentryBundle\Tests\DoctrineTestCase;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverConnection;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverForV2;
use Sentry\State\HubInterface;

final class TracingDriverForV2Test extends DoctrineTestCase
{
    /**
     * @var MockObject&HubInterface
     */
    private $hub;

    protected function setUp(): void
    {
        $this->hub = $this->createMock(HubInterface::class);
    }

    public static function setUpBeforeClass(): void
    {
        if (!self::isDoctrineDBALVersion2Installed()) {
            self::markTestSkipped();
        }
    }

    public function testConnect(): void
    {
        $databasePlatform = $this->createMock(AbstractPlatform::class);
        $databasePlatform->expects($this->once())
            ->method('getName')
            ->willReturn('foo');

        $decoratedDriver = $this->createMock(Driver::class);
        $decoratedDriver->expects($this->once())
            ->method('connect')
            ->with(['host' => 'localhost'], 'username', 'password', ['foo' => 'bar'])
            ->willReturn($this->createMock(DriverConnectionInterface::class));

        $decoratedDriver->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($databasePlatform);

        $driver = new TracingDriverForV2($this->hub, $decoratedDriver);

        $this->assertInstanceOf(TracingDriverConnection::class, $driver->connect(['host' => 'localhost'], 'username', 'password', ['foo' => 'bar']));
    }

    public function testGetDatabasePlatform(): void
    {
        $databasePlatform = $this->createMock(AbstractPlatform::class);

        $decoratedDriver = $this->createMock(Driver::class);
        $decoratedDriver->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($databasePlatform);

        $driver = new TracingDriverForV2($this->hub, $decoratedDriver);

        $this->assertSame($databasePlatform, $driver->getDatabasePlatform());
    }

    public function testGetSchemaManager(): void
    {
        $connection = $this->createMock(Connection::class);
        $databasePlatform = $this->createMock(AbstractPlatform::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);

        $decoratedDriver = $this->createMock(Driver::class);
        $decoratedDriver->expects($this->once())
            ->method('getSchemaManager')
            ->with($connection, $databasePlatform)
            ->willReturn($schemaManager);

        $driver = new TracingDriverForV2($this->hub, $decoratedDriver);

        $this->assertSame($schemaManager, $driver->getSchemaManager($connection, $databasePlatform));
    }

    public function testGetName(): void
    {
        $decoratedDriver = $this->createMock(Driver::class);
        $decoratedDriver->expects($this->once())
            ->method('getName')
            ->willReturn('foo');

        $driver = new TracingDriverForV2($this->hub, $decoratedDriver);

        $this->assertSame('foo', $driver->getName());
    }

    public function testGetDatabase(): void
    {
        $connection = $this->createMock(Connection::class);

        $decoratedDriver = $this->createMock(Driver::class);
        $decoratedDriver->expects($this->once())
            ->method('getDatabase')
            ->with($connection)
            ->willReturn('foo');

        $driver = new TracingDriverForV2($this->hub, $decoratedDriver);

        $this->assertSame('foo', $driver->getDatabase($connection));
    }

    public function testCreateDatabasePlatformForVersion(): void
    {
        $databasePlatform = $this->createMock(AbstractPlatform::class);

        $decoratedDriver = $this->createMock(StubVersionAwarePlatformDriver::class);
        $decoratedDriver->expects($this->once())
            ->method('createDatabasePlatformForVersion')
            ->with('5.7')
            ->willReturn($databasePlatform);

        $driver = new TracingDriverForV2($this->hub, $decoratedDriver);

        $this->assertSame($databasePlatform, $driver->createDatabasePlatformForVersion('5.7'));
    }

    public function testCreateDatabasePlatformForVersionWhenDriverDoesNotImplementInterface(): void
    {
        $databasePlatform = $this->createMock(AbstractPlatform::class);

        $decoratedDriver = $this->createMock(Driver::class);
        $decoratedDriver->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($databasePlatform);

        $driver = new TracingDriverForV2($this->hub, $decoratedDriver);

        $this->assertSame($databasePlatform, $driver->createDatabasePlatformForVersion('5.7'));
    }

    public function testConvertException(): void
    {
        $exception = $this->createMock(DriverException::class);
        $convertedException = new DBALDriverException('foo', $exception);

        $decoratedDriver = $this->createMock(StubExceptionConverterDriver::class);
        $decoratedDriver->expects($this->once())
            ->method('convertException')
            ->with('foo', $exception)
            ->willReturn($convertedException);

        $driver = new TracingDriverForV2($this->hub, $decoratedDriver);

        $this->assertSame($convertedException, $driver->convertException('foo', $exception));
    }

    public function testConvertExceptionWhenDriverDoesNotImplementInterface(): void
    {
        $exception = $this->createMock(DriverException::class);
        $driver = new TracingDriverForV2($this->hub, $this->createMock(Driver::class));

        $this->assertEquals(
            new DBALDriverException('foo', $exception),
            $driver->convertException('foo', $exception)
        );
    }
}

if (interface_exists(Driver::class)) {
    interface StubVersionAwarePlatformDriver extends Driver, VersionAwarePlatformDriver
    {
    }

    if (interface_exists(ExceptionConverterDriver::class)) {
        interface StubExceptionConverterDriver extends Driver, ExceptionConverterDriver
        {
        }
    } else {
        interface StubExceptionConverterDriver extends Driver
        {
        }
    }
}
