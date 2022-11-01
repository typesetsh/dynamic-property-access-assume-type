<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Handler;

use Psalm;
use Psalm\Plugin;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeVisitEvent;
use Psalm\Plugin\EventHandler\Event\PropertyExistenceProviderEvent;
use Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Storage;

/**
 * @psalm-suppress InternalMethod
 * @psalm-suppress InternalProperty
 */
class UniversalObjectCrate implements Plugin\EventHandler\AfterClassLikeVisitInterface
{
    public const DOC_TAG = 'universal-object-crate';

    public static function afterClassLikeVisit(AfterClassLikeVisitEvent $event): void
    {
        $storage = $event->getStorage();
        $codebase = $event->getCodebase();

        $property_exist = function (PropertyExistenceProviderEvent $event) use ($storage): ?bool {
            $name = $event->getPropertyName();
            $var_name = '$'.$name;
            if (isset($storage->properties[$name])) {
                return null;
            }

            $pseudo_get = $storage->pseudo_property_get_types[$var_name] ?? null;
            $pseudo_set = $storage->pseudo_property_set_types[$var_name] ?? null;

            if ($pseudo_get && $pseudo_set) {
                $property = $storage->properties[$name] = new Psalm\Storage\PropertyStorage();
                $property->type = $pseudo_set;

                $storage->declaring_property_ids[$name] = $storage->name;
                $storage->appearing_property_ids[$name] = $storage->name.'::'.$var_name;

                return true;
            }

            $object_crate_type = Storage::getTagType($storage, self::DOC_TAG);
            if ($object_crate_type) {
                $property = $storage->properties[$name] = new Psalm\Storage\PropertyStorage();
                $property->type = $object_crate_type;

                $storage->declaring_property_ids[$name] = $storage->name;
                $storage->appearing_property_ids[$name] = $storage->name.'::'.$var_name;
            }

            return null;
        };

        $codebase->properties->property_existence_provider->registerClosure($storage->name, $property_exist);
    }
}
