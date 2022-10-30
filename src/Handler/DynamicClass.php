<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Handler;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use Psalm\Internal\Scanner\DocblockParser;
use Psalm\Internal\Type\TypeCombiner;
use Psalm\Plugin;
use Psalm\Plugin\EventHandler\Event;
use Psalm\Storage;
use Psalm\Type;
use Psalm\Type\Atomic\TNamedObject;

/**
 * @psalm-suppress InternalMethod
 */
class DynamicClass implements Plugin\EventHandler\AfterExpressionAnalysisInterface
{
    public const DOC_TAG = 'dynamic-property-access-assume-type';

    /** @var array<string, bool> */
    private static array $assumeType = [];

    public static function afterExpressionAnalysis(Event\AfterExpressionAnalysisEvent $event): ?bool
    {
        $expr = $event->getExpr();
        $statementAnalyzer = $event->getStatementsSource();
        $codebase = $event->getCodebase();

        if (!$expr instanceof Expr\PropertyFetch) {
            return null;
        }
        if ($expr->name instanceof Identifier) {
            return null;
        }

        $nodeType = $statementAnalyzer->getNodeTypeProvider()->getType($expr->var);
        if (!$nodeType) {
            return null;
        }

        $assumed_types = [];
        foreach ($nodeType->getAtomicTypes() as $type) {
            if ($type instanceof TNamedObject) {
                $class_storage = $codebase->classlikes->getStorageFor($type->value);
                if ($class_storage) {
                    foreach (self::fetchMagicGetReturnTypes($class_storage) as $return_type) {
                        $assumed_types[] = $return_type;
                    }
                }
            } else {
                $assumed_types[] = $type;
            }
        }

        if ($assumed_types) {
            $assumedType = TypeCombiner::combine($assumed_types, $codebase);
            $statementAnalyzer->getNodeTypeProvider()->setType($expr, $assumedType);
        }

        return null;
    }

    /**
     * Retrieve return types from magic __get method if available and if DOC_TAG is set.
     *
     * @return list<Type\Atomic>
     */
    public static function fetchMagicGetReturnTypes(Storage\ClassLikeStorage $class_storage): array
    {
        $get_method = $class_storage->methods['__get']
                   ?? $class_storage->pseudo_methods['__get']
                   ?? null;
        if (!$get_method) {
            return [];
        }

        if (!self::assumeDynamicTypes($class_storage)) {
            return [];
        }

        $types = [];
        if ($get_method->return_type) {
            foreach ($get_method->return_type->getAtomicTypes() as $returnType) {
                $types[] = $returnType;
            }
        }

        return $types;
    }

    /**
     * Check class doc-block tags to see if feature is enabled.
     */
    public static function assumeDynamicTypes(Storage\ClassLikeStorage $class_storage): bool
    {
        if (isset(self::$assumeType[$class_storage->name])) {
            return self::$assumeType[$class_storage->name];
        }

        if (!$class_storage->stmt_location) {
            return false;
        }

        $snippet = $class_storage->stmt_location->getSnippet();

        $doc = DocblockParser::parse($snippet, 0);
        $assume = (bool) ($doc->tags[self::DOC_TAG] ?? false);

        return self::$assumeType[$class_storage->name] = $assume;
    }
}
