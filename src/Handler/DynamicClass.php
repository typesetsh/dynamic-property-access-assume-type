<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Handler;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use Psalm;
use Psalm\Internal\Type\TypeCombiner;
use Psalm\Plugin;
use Psalm\Plugin\EventHandler\Event;
use Psalm\Type;
use Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Storage;

/**
 * @psalm-suppress InternalMethod
 */
class DynamicClass implements Plugin\EventHandler\AfterExpressionAnalysisInterface
{
    public const DOC_TAG = 'dynamic-property-access-assume-type';

    public static function afterExpressionAnalysis(Event\AfterExpressionAnalysisEvent $event): ?bool
    {
        $expr = $event->getExpr();
        $statement_analyzer = $event->getStatementsSource();
        $codebase = $event->getCodebase();

        if (!$expr instanceof Expr\PropertyFetch) {
            return null;
        }
        if ($expr->name instanceof Identifier) {
            return null;
        }

        $node_type = $statement_analyzer->getNodeTypeProvider()->getType($expr->var);
        if (!$node_type) {
            return null;
        }

        $name_type = $statement_analyzer->getNodeTypeProvider()->getType($expr->name);

        /*
         * If name type is a list of literal strings, then we can assume that
         * not all properties are accessed, but only the literal strings.
         */
        $properties = $name_type ? self::fetchLiteralStrings($name_type) : [];

        $assumed_types = [];
        foreach ($node_type->getAtomicTypes() as $type) {
            if ($type instanceof Type\Atomic\TNamedObject) {
                $class_storage = $codebase->classlikes->getStorageFor($type->value);
                if ($class_storage) {
                    $magic_get_types = self::fetchMagicGetReturnTypes($class_storage);
                    if ($magic_get_types) {
                        foreach ($magic_get_types as $return_type) {
                            $assumed_types[] = $return_type;
                        }
                    } else {
                        foreach (AllowArrayCasting::collectPropertyTypes($class_storage, $properties) as $property_type) {
                            $assumed_types[] = $property_type;
                        }
                    }
                }
            } else {
                $assumed_types[] = $type;
            }
        }

        if ($assumed_types) {
            $union_type = TypeCombiner::combine($assumed_types, $codebase);
            $statement_analyzer->getNodeTypeProvider()->setType($expr, $union_type);
        }

        return null;
    }

    /**
     * Retrieve return types from magic __get method if available and if DOC_TAG is set.
     *
     * @return list<Type\Atomic>
     */
    public static function fetchMagicGetReturnTypes(Psalm\Storage\ClassLikeStorage $class_storage): array
    {
        $get_method = $class_storage->methods['__get']
                   ?? $class_storage->pseudo_methods['__get']
                   ?? null;

        $candidates = [];

        if (!$get_method || !Storage::hasTag($class_storage, self::DOC_TAG)) {
            return $candidates;
        }

        if ($get_method->return_type) {
            foreach ($get_method->return_type->getAtomicTypes() as $returnType) {
                $candidates[] = $returnType;
            }
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    public static function fetchLiteralStrings(Type\Union $types): array
    {
        $strings = [];
        foreach ($types->getAtomicTypes() as $type) {
            if ($type instanceof Type\Atomic\TLiteralString) {
                $strings[] = $type->value;
            } else {
                return [];
            }
        }

        return $strings;
    }
}
