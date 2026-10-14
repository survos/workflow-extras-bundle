<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/** Compile-time declarations for tooling; runtime support is resolved by Symfony's Registry. */
final readonly class WorkflowCatalog
{
    public function __construct(private ContainerInterface $workflows, private array $definitions)
    {
    }

    /** @return array<string, array{supports: list<class-string>, definition_class: ?class-string, type: string}> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<string> */
    public function forClass(string $class): array
    {
        $names = [];
        foreach ($this->definitions as $name => $definition) {
            foreach ($definition['supports'] as $supportedClass) {
                if (is_a($class, $supportedClass, true)) {
                    $names[] = $name;
                    break;
                }
            }
        }

        return $names;
    }

    public function get(string $name): WorkflowInterface
    {
        if (!isset($this->definitions[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown workflow "%s".', $name));
        }

        return $this->workflows->get($name);
    }
}
