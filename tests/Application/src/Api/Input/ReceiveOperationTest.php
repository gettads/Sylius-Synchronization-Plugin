<?php

namespace Tests\Gtt\SynchronizationPlugin\Application\Api\Input;

use PHPUnit\Framework\TestCase;
use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;

class ReceiveOperationTest extends TestCase
{

    /**
     * @test
     */
    public function I_can_get_and_set_all_properties_by_their_list()
    {
        $expectedProperties = ReceiveOperation::PROPERTIES;
        $reflectionClass = new \ReflectionClass(ReceiveOperation::class);
        $actualPropertyNames = array_map(
            fn(\ReflectionProperty $reflectionProperty) => $reflectionProperty->getName(),
            $reflectionClass->getProperties()
        );
        sort($expectedProperties);
        sort($actualPropertyNames);
        self::assertSame($expectedProperties, $actualPropertyNames, 'list of expected properties does not match real ones');

        foreach ($expectedProperties as $expectedProperty) {
            $methods = ['get' . ucfirst($expectedProperty), 'set' . ucfirst($expectedProperty)];
            foreach ($methods as $method) {
                self::assertTrue($reflectionClass->hasMethod($method), "Missing '$method'");
                $reflectionMethod = $reflectionClass->getMethod($method);
                self::assertTrue($reflectionMethod->isPublic(), "'$method' has to be public");
                self::assertFalse($reflectionMethod->isAbstract(), "'$method' could not be abstract");
            }
        }

        $data = [uniqid(), 'baz', 123];
        $receiveOperation = new ReceiveOperation('foo', 'bar', $data);
        self::assertSame('foo', $receiveOperation->getOperationId());
        self::assertSame('bar', $receiveOperation->getOperationCode());
        self::assertSame($data, $receiveOperation->getData());

        $receiveOperation->setOperationId('baz');
        $receiveOperation->setOperationCode('qux');
        $newData = ['different'];
        $receiveOperation->setData($newData);
        self::assertSame('baz', $receiveOperation->getOperationId());
        self::assertSame('qux', $receiveOperation->getOperationCode());
        self::assertSame($newData, $receiveOperation->getData());
    }
}
