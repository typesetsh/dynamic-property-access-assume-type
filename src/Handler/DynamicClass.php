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
    public static function fetchMagicGetReturnTypes(Storage\ClassLikeStorage $class_storage): array
    {
        $get_method = $class_storage->methods['__get']
                   ?? $class_storage->pseudo_methods['__get']
                   ?? null;

        $candidates = [];

        if (!$get_method || !self::assumeDynamicTypes($class_storage)) {
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
