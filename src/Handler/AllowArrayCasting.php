<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Handler;

use PhpParser\Node\Expr;
use Psalm;
use Psalm\Internal\Type\TypeCombiner;
use Psalm\Plugin;
use Psalm\Plugin\EventHandler\Event;
use Psalm\Type;
use Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Storage;

/**
 * @psalm-suppress InternalMethod
 */
class AllowArrayCasting implements Plugin\EventHandler\AfterExpressionAnalysisInterface
{
    public const DOC_TAG = 'allow-array-casting';

    public static function afterExpressionAnalysis(Event\AfterExpressionAnalysisEvent $event): ?bool
    {
        $expr = $event->getExpr();
        $statementAnalyzer = $event->getStatementsSource();
        $codebase = $event->getCodebase();

        if (!$expr instanceof Expr\Cast\Array_) {
            return null;
        }

        $node_type = $statementAnalyzer->getNodeTypeProvider()->getType($expr->expr);
        if (!$node_type) {
            return null;
        }

        $candidates = [];
        foreach ($node_type->getAtomicTypes() as $atomic_type) {
            if ($atomic_type instanceof Type\Atomic\TNamedObject) {
                $class_storage = $codebase->classlikes->getStorageFor($atomic_type->value);

                if (!$class_storage || !Storage::hasTag($class_storage, self::DOC_TAG)) {
                    return null;
                }

                foreach (self::collectPropertyTypes($class_storage) as $property_type) {
                    $candidates[] = $property_type;
                }
            }
        }

        if ($candidates) {
            $type = new Type\Atomic\TArray(
                [
                    new Type\Union([new Type\Atomic\TString()]),
                    TypeCombiner::combine($candidates, $codebase),
                ]
            );

            $statementAnalyzer->getNodeTypeProvider()->setType($expr, new Type\Union([$type]));
        }

        return null;
    }

    /**
     * @param list<string> $properties
     *
     * @return list<Type\Atomic>
     */
    public static function collectPropertyTypes(Psalm\Storage\ClassLikeStorage $class_storage, array $properties = []): array
    {
        $candidates = [];
        foreach ($class_storage->properties as $name => $property) {
            if ($properties && !in_array($name, $properties)) {
                continue;
            }
            if ($property->type) {
                foreach ($property->type->getAtomicTypes() as $propertyType) {
                    $candidates[] = $propertyType;
                }
            }
        }

        return $candidates;
    }
}
