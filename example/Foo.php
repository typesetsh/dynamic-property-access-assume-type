<?php

declare(strict_types=1);

namespace Typesetsh\DynamicProperties\Example;

/**
 * @dynamic-property-access-assume-type
 */
class B
{
    public string $name = '';

    public function __get(string $name): int
    {
        throw new \RuntimeException('No such property');
    }
}

/**
 * @dynamic-property-access-assume-type
 */
class A
{
    public function __get(string $name): B
    {
        throw new \RuntimeException('No such property');
    }
}

$prop1 = (string) rand(1000, 9999);
$prop2 = (string) rand(1000, 9999);

$a = new A();

return $a->{$prop1}->{$prop2};
