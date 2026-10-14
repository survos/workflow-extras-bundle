<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Attribute;

/** @internal */
final class Metadata
{
    public static function enhance(array $metadata, string|array|null $next = null, ?string $label = null, ?string $icon = null, ?string $iconClass = null): array
    {
        if (null !== $next) {
            $next = self::normalizeNext($next);
            if (array_key_exists('next', $metadata) && self::normalizeNext($metadata['next']) !== $next) {
                throw new \InvalidArgumentException('Use the "next" argument instead of conflicting metadata.');
            }
            $metadata['next'] = $next;
        } elseif (array_key_exists('next', $metadata)) {
            $metadata['next'] = self::normalizeNext($metadata['next']);
        }
        if (null !== $label) {
            if (array_key_exists('label', $metadata) && $metadata['label'] !== $label) {
                throw new \InvalidArgumentException('Use the "label" argument instead of conflicting metadata.');
            }
            $metadata['label'] = $label;
        }

        foreach (['icon' => $icon, 'iconClass' => $iconClass] as $key => $value) {
            if (null === $value) {
                continue;
            }
            if (array_key_exists($key, $metadata) && $metadata[$key] !== $value) {
                throw new \InvalidArgumentException(sprintf('Use the "%s" argument instead of conflicting metadata.', $key));
            }
            $metadata[$key] = $value;
        }

        return $metadata;
    }

    /** @return list<string> */
    private static function normalizeNext(mixed $next): array
    {
        $next = is_string($next) ? [$next] : $next;
        if (!is_array($next) || !array_is_list($next)) {
            throw new \InvalidArgumentException('Next transitions must be a string or an ordered list.');
        }
        foreach ($next as $name) {
            if (!is_string($name) || '' === trim($name)) {
                throw new \InvalidArgumentException('Next transition names must be non-empty strings.');
            }
        }

        return $next;
    }
}
