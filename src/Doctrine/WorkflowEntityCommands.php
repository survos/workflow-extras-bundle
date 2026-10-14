<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Survos\WorkflowExtrasBundle\Contract\TransitionExecutorInterface;
use Survos\WorkflowExtrasBundle\Service\NextTransitionRunner;
use Survos\WorkflowExtrasBundle\Service\WorkflowCatalog;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * state:iterate and state:stats for Doctrine entities a workflow `supports`.
 *
 * Transitions go through the configured executor: applied directly with extras
 * alone, queued with workflow-messenger (or handled in-process with --sync).
 */
final readonly class WorkflowEntityCommands
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private WorkflowCatalog $catalog,
        private TransitionExecutorInterface $executor,
        private NextTransitionRunner $next,
    ) {
    }

    #[AsCommand('state:iterate', 'Apply or queue a workflow transition for each matching entity.', aliases: ['iterate'])]
    public function iterate(
        SymfonyStyle $io,
        #[Argument('Entity class or short name')] string $className,
        #[Option('Transition to run; default: the first enabled `next` of each entity\'s place', shortcut: 't')] ?string $transition = null,
        #[Option('Comma-separated places to select', shortcut: 'm')] ?string $marking = null,
        #[Option('Workflow name, when several support the class', shortcut: 'w')] ?string $workflowName = null,
        #[Option('Handle every transition in this process, including continuations')] bool $sync = false,
        #[Option('Continuation after the transition: async, sync or none')] ?string $cascade = null,
        #[Option('Maximum number of transitions; zero means all')] int $limit = 0,
        #[Option('Field filters as a query string, e.g. vendor=symfony')] string $filter = '',
    ): int {
        [$class, $name] = $this->resolve($className, $workflowName);
        $workflow = $this->catalog->get($name);
        $cascade ??= $sync ? 'sync' : 'async';
        if (!in_array($cascade, ['async', 'sync', 'none'], true) || $limit < 0) {
            throw new \InvalidArgumentException('--cascade must be async, sync or none, and --limit zero or more.');
        }
        if (null !== $transition && !in_array($transition, array_map(static fn ($t) => $t->getName(), $workflow->getDefinition()->getTransitions()), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown transition "%s"; valid: %s.', $transition, implode(', ', array_unique(array_map(static fn ($t) => $t->getName(), $workflow->getDefinition()->getTransitions())))));
        }
        $places = null === $marking ? null : $this->places($workflow, $marking);
        $em = $this->manager($class);
        $query = $em->createQueryBuilder()->select('e')->from($class, 'e');
        $this->applyFilters($em, $class, $query, $filter);
        $column = $this->column($name);
        if (null !== $places && null !== $column) {
            $query->andWhere(sprintf('e.%s IN (:places)', $column))->setParameter('places', $places);
        }

        $run = function () use ($query, $em, $workflow, $transition, $places, $cascade, $limit, $io): array {
            $count = $skipped = 0;
            $progress = $io->createProgressBar();
            foreach ($query->getQuery()->toIterable() as $subject) {
                $progress->advance();
                if (null !== $places && !array_intersect($places, array_keys($workflow->getMarking($subject)->getPlaces()))) {
                    continue;
                }
                if (null === $transition) {
                    // The place's own `next`, as a kickoff would have run it.
                    $started = null !== $this->next->start($workflow, $subject, ['cascade' => $cascade]);
                } elseif ($started = $workflow->can($subject, $transition)) {
                    $this->executor->execute($workflow, $subject, $transition, ['cascade' => $cascade]);
                }
                $started ? ++$count : ++$skipped;
                if (!$this->executor->requiresSavedSubject()) {
                    $em->flush();
                }
                $em->clear();
                if ($limit && $count >= $limit) {
                    break;
                }
            }
            $progress->finish();
            $io->newLine();

            return [$count, $skipped];
        };
        [$count, $skipped] = $sync ? $this->executor->synchronously($run) : $run();
        $handled = $sync || !$this->executor->requiresSavedSubject() ? 'applied' : 'queued';
        $io->success(sprintf('%s %s: %d %s, %d skipped (not enabled).', $class, $transition ?? 'next', $count, $handled, $skipped));

        return Command::SUCCESS;
    }

    #[AsCommand('state:stats', 'Count workflow entities by place.')]
    public function stats(
        SymfonyStyle $io,
        #[Argument('Entity class or short name')] string $className,
        #[Option('Workflow name, when several support the class', shortcut: 'w')] ?string $workflowName = null,
    ): int {
        [$class, $name] = $this->resolve($className, $workflowName);
        $workflow = $this->catalog->get($name);
        $em = $this->manager($class);
        $counts = [];
        if (($column = $this->column($name)) !== null) {
            $rows = $em->createQueryBuilder()->select(sprintf('e.%s AS place, COUNT(e) AS total', $column))
                ->from($class, 'e')->groupBy('e.'.$column)->getQuery()->getArrayResult();
            foreach ($rows as $row) {
                $counts[self::place($row['place'])] = (int) $row['total'];
            }
        } else {
            foreach ($em->createQueryBuilder()->select('e')->from($class, 'e')->getQuery()->toIterable() as $subject) {
                foreach (array_keys($workflow->getMarking($subject)->getPlaces()) as $place) {
                    $counts[$place] = ($counts[$place] ?? 0) + 1;
                }
                $em->clear();
            }
        }
        $definition = $workflow->getDefinition();
        $from = [];
        foreach ($definition->getTransitions() as $t) {
            foreach ($t->getFroms() as $place) {
                $from[$place][$t->getName()] = true;
            }
        }
        $rows = [];
        foreach ($definition->getPlaces() as $place) {
            $rows[] = [$place, $counts[$place] ?? 0, implode(', ', array_keys($from[$place] ?? []))];
            unset($counts[$place]);
        }
        foreach ($counts as $place => $total) {
            $rows[] = [$place.' (undeclared)', $total, ''];
        }
        $io->title(sprintf('%s (%s)', $class, $name));
        $io->table(['Place', 'Count', 'Transitions'], $rows);

        return Command::SUCCESS;
    }

    /** @return array{class-string, string} */
    private function resolve(string $className, ?string $workflowName): array
    {
        $classes = [];
        foreach ($this->catalog->all() as $definition) {
            foreach ($definition['supports'] as $supported) {
                $classes[$supported] = true;
            }
        }
        $matches = array_values(array_filter(array_keys($classes), static fn (string $class) => 0 === strcasecmp($class, ltrim($className, '\\'))
            || 0 === strcasecmp(substr(strrchr('\\'.$class, '\\'), 1), $className)));
        $class = match (count($matches)) {
            1 => $matches[0],
            0 => class_exists($className) ? $className : throw new \InvalidArgumentException(sprintf('No workflow supports "%s"; supported: %s.', $className, implode(', ', array_keys($classes)) ?: 'none')),
            default => throw new \InvalidArgumentException(sprintf('"%s" is ambiguous: %s.', $className, implode(', ', $matches))),
        };
        $names = $this->catalog->forClass($class);
        if (null !== $workflowName) {
            if (!in_array($workflowName, $names, true)) {
                throw new \InvalidArgumentException(sprintf('Workflow "%s" does not support %s; use one of: %s.', $workflowName, $class, implode(', ', $names) ?: 'none'));
            }

            return [$class, $workflowName];
        }

        return match (count($names)) {
            1 => [$class, $names[0]],
            0 => throw new \InvalidArgumentException(sprintf('No workflow declares support for %s.', $class)),
            default => throw new \InvalidArgumentException(sprintf('Several workflows support %s; pass --workflow-name: %s.', $class, implode(', ', $names))),
        };
    }

    private function manager(string $class): EntityManagerInterface
    {
        $em = $this->doctrine->getManagerForClass($class);
        if (!$em instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('%s is not a Doctrine ORM entity.', $class));
        }

        return $em;
    }

    /** The column holding a state machine's single place; null when markings must be read per entity. */
    private function column(string $name): ?string
    {
        $definition = $this->catalog->all()[$name];

        return 'state_machine' === $definition['type'] ? $definition['marking_property'] : null;
    }

    /** @return list<string> */
    private function places(WorkflowInterface $workflow, string $marking): array
    {
        $places = array_values(array_filter(array_map('trim', explode(',', $marking)), 'strlen'));
        $known = $workflow->getDefinition()->getPlaces();
        foreach ($places as $place) {
            if (!isset($known[$place])) {
                throw new \InvalidArgumentException(sprintf('Unknown place "%s"; valid: %s.', $place, implode(', ', $known)));
            }
        }

        return $places;
    }

    private function applyFilters(EntityManagerInterface $em, string $class, QueryBuilder $query, string $filter): void
    {
        parse_str($filter, $filters);
        $metadata = $em->getClassMetadata($class);
        foreach ($filters as $field => $value) {
            if (!$metadata->hasField($field) || !is_scalar($value)) {
                throw new \InvalidArgumentException(sprintf('Unsupported filter "%s"; filters are scalar fields of %s.', $field, $class));
            }
            if ('boolean' === $metadata->getTypeOfField($field)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? throw new \InvalidArgumentException(sprintf('Filter "%s" must be a boolean.', $field));
            }
            $parameter = 'filter_'.count($query->getParameters());
            $query->andWhere(sprintf('e.%s = :%s', $field, $parameter))->setParameter($parameter, $value);
        }
    }

    private static function place(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
