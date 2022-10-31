<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Handler;

use PhpParser\Node\Expr;
use Psalm\Internal\Scanner\DocblockParser;
use Psalm\Internal\Type\TypeCombiner;
use Psalm\Plugin;
use Psalm\Plugin\EventHandler\Event;
use Psalm\Storage;
use Psalm\Type;
use Psalm\Type\Atomic\TArray;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;

/**
 * @psalm-suppress InternalMethod
 */
class AllowArrayCasting implements Plugin\EventHandler\AfterExpressionAnalysisInterface
{
    public const DOC_TAG = 'allow-array-casting';

    /** @var array<string, bool> */
    private static array $iterableClasses = [];

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
            if ($atomic_type instanceof TNamedObject) {
                $class_storage = $codebase->classlikes->getStorageFor($atomic_type->value);

                if (!$class_storage || !self::isIterableObject($class_storage)) {
                    return null;
                }

                foreach ($class_storage->properties as $property) {
                    if ($property->type) {
                        foreach ($property->type->getAtomicTypes() as $propertyType) {
                            $candidates[] = $propertyType;
                        }
                    }
                }
            }
        }

        if ($candidates) {
            $type = new TArray(
                [
                    new Union([new Type\Atomic\TString()]),
                    TypeCombiner::combine($candidates, $codebase),
                ]
            );

            $statementAnalyzer->getNodeTypeProvider()->setType($expr, new Union([$type]));
        }

        return null;
    }

    /**
     * Check class doc-block tags to see if feature is enabled.
     */
    public static function isIterableObject(Storage\ClassLikeStorage $class_storage): bool
    {
        if (isset(self::$iterableClasses[$class_storage->name])) {
            return self::$iterableClasses[$class_storage->name];
        }

        if (!$class_storage->stmt_location) {
            return false;
        }

        $snippet = $class_storage->stmt_location->getSnippet();

        $doc = DocblockParser::parse($snippet, 0);
        $assume = (bool) ($doc->tags[self::DOC_TAG] ?? false);

        return self::$iterableClasses[$class_storage->name] = $assume;
    }
}
