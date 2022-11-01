<?php
/**
 * Some file doc comment.
 *
 * @see LICENSE.md
 */
declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Example;

/**
 * @universal-object-crate bool|int|null
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

$m = new M();
$m->age = 34;

$n = new N();
$n->name = 'John';
$n->age = 34;
$n->good = true;

return [$m, $n];
