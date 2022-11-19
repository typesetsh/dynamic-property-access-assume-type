<?php
/**
 * Some file doc comment.
 *
 * @see LICENSE.md
 */
declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Example;

/**
 * @psalm-type test = bool|int|null
 * @psalm-type test2 = test|string
 *
 * @universal-object-crate test2
 */
class M extends \stdClass
{
}

/**
 * @property int $age
 */
class N extends M
{
    public string $name = '';
}

class O extends M
{
    public ?N $n = null;
}
class Q extends O
{
}

$m = new O();
$m->age = 34;

$n = new Q();
$n->name = 'John';
$n->age = 34;
$n->good = true;
$n->n = new N();

return [$m, $n];
