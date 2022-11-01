<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType;

use Psalm;
use Psalm\Exception\DocblockParseException;
use Psalm\Exception\TypeParseTreeException;
use Psalm\Internal\Scanner\DocblockParser;
use Psalm\Internal\Type\TypeParser;
use Psalm\Internal\Type\TypeTokenizer;

class Storage
{
    /** @var array<string, array<string, array<int, string>>> */
    private static array $tags = [];

    /**
     * @param list<string> $properties
     *
     * @return list<Psalm\Type\Atomic>
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

    /**
     * Check class has given doc-block.
     */
    public static function hasTag(Psalm\Storage\ClassLikeStorage $class_storage, string $tag): bool
    {
        return isset(self::fetchTags($class_storage)[$tag]);
    }

    /**
     * Get type for given tag.
     */
    public static function getTagType(Psalm\Storage\ClassLikeStorage $class_storage, string $tag): ?Psalm\Type\Union
    {
        $type_string = Storage::getTag($class_storage, $tag);
        if (!$type_string) {
            return null;
        }

        if (!$class_storage->aliases) {
            return null;
        }

        try {
            $type_tokens = TypeTokenizer::getFullyQualifiedTokens(
                $type_string,
                $class_storage->aliases,
                null,
                null,
                $class_storage->name
            );
        } catch (TypeParseTreeException $e) {
            throw new DocblockParseException($type_string.' is not a valid type');
        }

        return TypeParser::parseTokens($type_tokens);
    }

    /**
     * Check class has given doc-block.
     */
    public static function getTag(Psalm\Storage\ClassLikeStorage $class_storage, string $tag): ?string
    {
        $values = self::fetchTags($class_storage)[$tag] ?? [];

        foreach ($values as $value) {
            return strstr($value, "\n", true);
        }

        return null;
    }

    /**
     * Fetch all tags for given class.
     *
     * @return array<string, array<int, string>>
     */
    public static function fetchTags(Psalm\Storage\ClassLikeStorage $class_storage): array
    {
        if (isset(self::$tags[$class_storage->name])) {
            return self::$tags[$class_storage->name];
        }

        if (!$class_storage->stmt_location) {
            return [];
        }

        $doc = DocblockParser::parse($class_storage->stmt_location->getSnippet(), 0);
        self::$tags[$class_storage->name] = $doc->tags;

        return self::$tags[$class_storage->name];
    }
}
