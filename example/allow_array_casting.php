<?php
/**
 * Some file doc comment.
 *
 * @see LICENSE.md
 */
declare(strict_types=1);

namespace Typesetsh\DynamicProperties\Example;

/**
 * @allow-array-casting
 */
class Data
{
    public string $name = '';
    public int $age = 0;
}

$data = new Data();

foreach ((array) $data as $key => $value) {
    echo $key, $value;
}
