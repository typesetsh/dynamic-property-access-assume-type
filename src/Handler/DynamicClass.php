<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType\Handler;

use PhpParser\Node\Expr;
use Psalm\Internal\Scanner\DocblockParser;
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
        if (!$expr->name instanceof Expr\Variable) {
            return null;
        }

        $nodeType = $statementAnalyzer->getNodeTypeProvider()->getType($expr->var);
        if (!$nodeType) {
            return null;
        }

        $assumedTypes = [];
        foreach ($nodeType->getAtomicTypes() as $type) {
            if (!$type instanceof TNamedObject) {
                return null;
            }

            $class_storage = $codebase->classlikes->getStorageFor($type->value);
            if (!$class_storage) {
                return null;
            }

            $get_method = $class_storage->methods['__get'] ?? null;
            if (!$get_method) {
                return null;
            }

            if (!self::assumeDynamicTypes($class_storage)) {
                return null;
            }

            if ($get_method->return_type) {
                $assumedTypes[] = $get_method->return_type;
            }
        }

        if ($assumedTypes) {
            $assumedType = Type::combineUnionTypeArray($assumedTypes, $codebase);
            $statementAnalyzer->getNodeTypeProvider()->setType($expr, $assumedType);
        }

        return null;
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

        $docblock = file_get_contents($class_storage->stmt_location->file_path);

        $doc = DocblockParser::parse($docblock, $class_storage->stmt_location->docblock_start ?? 0);
        $assume = (bool) ($doc->tags[self::DOC_TAG] ?? false);

        return self::$assumeType[$class_storage->name] = $assume;
    }
}
