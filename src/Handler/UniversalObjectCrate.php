<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Handler;

use Psalm;
use Psalm\Plugin;
use Psalm\Plugin\EventHandler\Event\AfterCodebasePopulatedEvent;
use Psalm\Plugin\EventHandler\Event\PropertyExistenceProviderEvent;
use Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Storage;

/**
 * @psalm-suppress InternalMethod
 * @psalm-suppress InternalProperty
 */
class UniversalObjectCrate implements Plugin\EventHandler\AfterCodebasePopulatedInterface
{
    public const DOC_TAG = 'universal-object-crate';

    public static function afterCodebasePopulated(AfterCodebasePopulatedEvent $event)
    {
        $codebase = $event->getCodebase();
        $classes = $codebase->classlike_storage_provider->getAll();

        foreach ($classes as $class_storage) {
            $object_crate_type = self::getCrateType($codebase, $class_storage);
            if (!$object_crate_type) {
                continue;
            }

            $property_exist = function (PropertyExistenceProviderEvent $event) use ($class_storage, $object_crate_type): ?bool {
                $name = $event->getPropertyName();
                $var_name = '$'.$name;
                if (isset($class_storage->properties[$name])) {
                    return null;
                }

                $pseudo_get = $class_storage->pseudo_property_get_types[$var_name] ?? null;
                $pseudo_set = $class_storage->pseudo_property_set_types[$var_name] ?? null;

                if ($pseudo_get && $pseudo_set) {
                    $property = $class_storage->properties[$name] = new Psalm\Storage\PropertyStorage();
                    $property->type = $pseudo_set;

                    $class_storage->declaring_property_ids[$name] = $class_storage->name;
                    $class_storage->appearing_property_ids[$name] = $class_storage->name.'::'.$var_name;
                } else {
                    $property = $class_storage->properties[$name] = new Psalm\Storage\PropertyStorage();
                    $property->type = $object_crate_type;

                    $class_storage->declaring_property_ids[$name] = $class_storage->name;
                    $class_storage->appearing_property_ids[$name] = $class_storage->name.'::'.$var_name;
                }

                return true;
            };

            $codebase->properties->property_existence_provider->registerClosure($class_storage->name, $property_exist);
        }
    }

    private static function getCrateType(
        Psalm\Codebase $codebase,
        Psalm\Storage\ClassLikeStorage $class_storage
    ): ?Psalm\Type\Union {
        try {
            $type = Storage::getTagType($codebase, $class_storage, self::DOC_TAG);
            if ($type) {
                return $type;
            }

            foreach ($class_storage->parent_classes as $parent_class) {
                $parent_storage = $codebase->classlike_storage_provider->get($parent_class);
                $type = Storage::getTagType($codebase, $parent_storage, self::DOC_TAG);
                if ($type) {
                    return $type;
                }
            }
        } catch (\Exception $_) {
            return null;
        }

        return null;
    }
}
