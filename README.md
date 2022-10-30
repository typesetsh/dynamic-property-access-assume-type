# Assume type when accessing properties dynamically

Allow assuming type when accessing properties dynamically using the __get() method.


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