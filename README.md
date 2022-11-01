# Assume type when accessing properties dynamically

Allow assuming type when accessing properties dynamically using the __get() method.


Add `@universal-object-crate` for classes with type declaration.
```php
<?php
/**
 * Some file doc comment.
 *
 * @see LICENSE.md
 */
declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Example;

/**
 * @universal-object-crate int|null
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

return [$m, $n];


```


Add `@allow-array-casting` for casting to array of type `array<string, {union of all properties}>`.

```php

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
class Data extends \stdClass
{
    public string $name = '';
    public int $age = 0;
}

$data = new Data();

foreach ((array) $data as $key => $value) {
    echo $key, $value;
}


```



```php

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

// dynamic property access
$prop1 = (string) rand(1000, 9999);
$prop2 = (string) rand(1000, 9999);

$a = new A();

return $a->{$prop1}->{$prop2};
```



