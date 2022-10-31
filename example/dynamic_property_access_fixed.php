<?php
/**
 * Some file doc comment.
 *
 * @see LICENSE.md
 */
declare(strict_types=1);

namespace Typesetsh\DynamicProperties\Example;

class Person
{
    public string $firstname = '';
    public string $lastname = '';
    public int $age = 0;
    public ?Person $parent = null;
}

$a = new Person();

/** @var 'firstname'|'lastname' $prop */
$prop = 'lastname';

return $a->{$prop}.'test';
