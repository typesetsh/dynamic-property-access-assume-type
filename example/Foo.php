<?php
/**
 * Some file doc comment.
 *
 * @see LICENSE.md
 */
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
 * @method ?B __get(string $name)
 * @dynamic-property-access-assume-type
 */
class A
{
}

class C
{
    public int $value;

    public function __construct()
    {
        $this->value = rand(0, 1000);
    }
}

$prop2 = rand(1000, 9999);

$c = new C();
$a = new A();

return $a->{$c->value}->{$prop2};
