<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Service;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\DumperInterface;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;

/**
 * GraphvizDumper dumps a workflow as a graphviz file.
 *
 * You can convert the generated dot file with the dot utility (https://graphviz.org/):
 *
 *   dot -Tpng workflow.dot > workflow.png
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class WorkflowGraphvizDumper implements DumperInterface
{
    // All values should be strings
    protected static $defaultOptions = [
        'graph' => ['ratio' => 'compress', 'rankdir' => 'LR'],
        'node' => ['fontsize' => '9', 'fontname' => 'Arial', 'color' => '#333333', 'fillcolor' => 'lightblue', 'fixedsize' => 'false', 'width' => '1'],
        'edge' => ['fontsize' => '9', 'fontname' => 'Arial', 'color' => '#333333', 'arrowhead' => 'normal', 'arrowsize' => '0.5'],
    ];

    /**
     * {@inheritdoc}
     *
     * Dumps the workflow as a graphviz graph.
     *
     * Available options:
     *
     *  * graph: The default options for the whole graph
     *  * node: The default options for nodes (places)
     *  * edge: The default options for edges
     */
    public function dump(Definition $definition, ?Marking $marking = null, array $options = []): string
    {
        $places = $this->findPlaces($definition, $marking);
        $edges = $this->findEdges($definition);

        $options = array_replace_recursive(self::$defaultOptions, $options);

        return $this->startDot($options)
            .$this->addPlaces($places)
            .$this->addEdges($edges)
            .$this->endDot()
        ;
    }

    protected function findPlaces(Definition $definition, ?Marking $marking = null): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $places = [];

        foreach ($definition->getPlaces() as $place) {
            $attributes = ['shape' => 'box', 'style' => 'rounded,filled', 'fillcolor' => '#ffffff', 'color' => '#cbd5e1', 'fontcolor' => '#243247', 'penwidth' => '1.4'];
            $hasOutgoing = false;
            foreach ($definition->getTransitions() as $transition) {
                if (\in_array($place, $transition->getFroms(), true)) {
                    $hasOutgoing = true;
                    break;
                }
            }
            if (!$hasOutgoing) {
                $attributes['fillcolor'] = '#f1f5f9';
                $attributes['peripheries'] = '2';
            }
            if (\in_array($place, $definition->getInitialPlaces(), true)) {
                $attributes['style'] = 'rounded,filled';
            }
            if ($marking?->has($place)) {
                $attributes['color'] = '#2563eb';
                $attributes['fillcolor'] = '#eff6ff';
                $attributes['penwidth'] = '2';
            }
            $backgroundColor = $workflowMetadata->getMetadata('bgColor', $place) ?? $workflowMetadata->getMetadata('bg_color', $place);
            if (null !== $backgroundColor) {
                $attributes['style'] = 'rounded,filled';
                // Semantic UI colors are not Graphviz color names.
                // Use pale fills with the default dark label for readable diagrams.
                $attributes['fillcolor'] = match ($backgroundColor) {
                    'primary' => '#dbeafe',
                    'secondary' => '#e2e8f0',
                    'success' => '#dcfce7',
                    'danger' => '#fee2e2',
                    'warning' => '#fef3c7',
                    'info' => '#cffafe',
                    'light' => '#f8fafc',
                    'dark' => '#334155',
                    default => $backgroundColor,
                };
                if ('dark' === $backgroundColor) {
                    $attributes['fontcolor'] = '#ffffff';
                }
            }
            $label = $workflowMetadata->getMetadata('label', $place);
            if (null !== $label) {
                $attributes['name'] = $label;
            }
            // full description shows on hover (graphviz emits it as an SVG xlink:title)
            $description = $workflowMetadata->getMetadata('description', $place);
            $attributes['tooltip'] = $this->normalizeTooltip($description ?? $label ?? $place);
            $places[$place] = [
                'attributes' => $attributes,
            ];
        }

        return $places;
    }

    protected function startDot(array $options): string
    {
        return sprintf(
            "digraph workflow {\n  %s\n  node [%s];\n  edge [%s];\n\n",
            $this->addOptions($options['graph']),
            $this->addOptions($options['node']),
            $this->addOptions($options['edge'])
        );
    }

    /**
     * @internal
     */
    protected function endDot(): string
    {
        return "}\n";
    }

    private function addOptions(array $options): string
    {
        $code = [];

        foreach ($options as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }

    protected function dotize(string $id): string
    {
        return $id;
    }

    /**
     * @internal
     */
    protected function findEdges(Definition $definition): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $edges = [];

        foreach ($definition->getTransitions() as $index => $transition) {
            $attributes = [];

            $transitionName = $workflowMetadata->getMetadata('label', $transition) ?? ucwords(str_replace('_', ' ', $transition->getName()));
            $async = true === $workflowMetadata->getMetadata('async', $transition);

            $labelColor = $workflowMetadata->getMetadata('color', $transition);
            if (null !== $labelColor) {
                $attributes['fontcolor'] = $labelColor;
            }
            $arrowColor = $workflowMetadata->getMetadata('arrow_color', $transition);
            if (null !== $arrowColor) {
                $attributes['color'] = $arrowColor;
            }
            // full description shows on hover (graphviz emits it as an SVG xlink:title)
            $description = $workflowMetadata->getMetadata('description', $transition);
            $attributes['tooltip'] = $this->normalizeTooltip($description ?? $transitionName);
            if ($async) {
                $attributes['tooltip'] .= ' — Async: queued for a worker';
            }
            $guard = $workflowMetadata->getMetadata('guard', $transition);
            if (is_string($guard) && '' !== $guard) {
                $attributes['tooltip'] .= ' — Guard: '.$this->normalizeTooltip($guard);
            }

            $guardText = is_string($guard) ? $guard : '';
            $guardLabel = $workflowMetadata->getMetadata('guardLabel', $transition);
            $froms = $transition->getFroms();
            $tos = $transition->getTos();

            // A fork or join (several froms or tos) is drawn through one small bar, not a from x to fan of edges.
            if (\count($froms) > 1 || \count($tos) > 1) {
                $hub = 'hub_'.$index;
                $attributes['id'] = sprintf('wf-transition-%d-0', $index);
                $hubAttributes = ['tooltip' => $attributes['tooltip']];
                $edges[$hub.'__node'] = [
                    'hub' => $hub,
                    'name' => $transitionName,
                    'async' => $async,
                    'guard' => $guardText,
                    'guardLabel' => $guardLabel,
                    'attributes' => $hubAttributes,
                ];
                $path = 0;
                foreach ($froms as $from) {
                    $attributes['id'] = sprintf('wf-transition-%d-%d', $index, $path++);
                    $edges[] = ['from' => $from, 'to' => $hub, 'name' => '', 'async' => $async, 'guard' => '', 'guardLabel' => null, 'attributes' => $attributes + ['arrowhead' => 'none']];
                }
                foreach ($tos as $to) {
                    $attributes['id'] = sprintf('wf-transition-%d-%d', $index, $path++);
                    $edges[] = ['from' => $hub, 'to' => $to, 'name' => '', 'async' => $async, 'guard' => '', 'guardLabel' => null, 'attributes' => $attributes];
                }
                continue;
            }

            $attributes['id'] = sprintf('wf-transition-%d-0', $index);
            $edges[] = [
                'from' => $froms[0],
                'to' => $tos[0],
                'name' => $transitionName,
                'async' => $async,
                'guard' => $guardText,
                'guardLabel' => $guardLabel,
                'attributes' => $attributes,
            ];
        }

        return $edges;
    }

    /**
     * @internal
     */
    protected function addEdges(array $edges): string
    {
        $code = '';

        foreach ($edges as $edge) {
            if (isset($edge['hub'])) {
                $code .= sprintf(
                    "  place_%s [label=%s, shape=box, style=\"rounded,filled\", fillcolor=\"#e2e8f0\", color=\"#94a3b8\", fontcolor=\"#334155\", fontsize=11, margin=\"0.12,0.06\", class=\"wf-hub\"%s];\n",
                    $this->dotize($edge['hub']),
                    $this->edgeLabel($edge['name'], $edge['guard'], $edge['guardLabel'], $edge['async']),
                    $this->addAttributes($edge['attributes'])
                );
                continue;
            }
            $label = '' === $edge['name'] ? '""' : $this->edgeLabel($edge['name'], $edge['guard'], $edge['guardLabel'], $edge['async']);
            $code .= sprintf(
                "  place_%s -> place_%s [label=%s style=\"%s\"%s];\n",
                $this->dotize($edge['from'] ?? ''),
                $this->dotize($edge['to'] ?? ''),
                $label,
                $edge['async'] ? 'dashed' : 'solid',
                $this->addAttributes($edge['attributes'])
            );
        }

        return $code;
    }

    protected function addPlaces(array $places): string
    {
        $code = '';

        foreach ($places as $id => $place) {
            if (isset($place['attributes']['name'])) {
                $placeName = $place['attributes']['name'];
                unset($place['attributes']['name']);
            } else {
                $placeName = ucwords(str_replace('_', ' ', $id));
            }

            if (isset($place['attributes']['shape'])) {
                $shape = $place['attributes']['shape'];
                unset($place['attributes']['shape']);
            } else {
                $shape = 'ellipse';
            }
            assert(!empty($shape), json_encode($place));

            $code .= sprintf(
                "  place_%s [label=\"%s\", shape=%s%s];\n",
                $this->dotize($id),
                $this->escape($placeName),
                $shape,
                $this->addAttributes($place['attributes'])
            );
        }
        //    dd($code);

        return $code;
    }

    private function edgeLabel(string $name, string $guard, ?string $guardLabel = null, bool $async = false): string
    {
        $html = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $async ? '◷ <I>'.$html($name).'</I>' : $html($name);
        if ('' === $guard) {
            return $async ? '<'.$title.'>' : '"'.$this->escape($name).'"';
        }

        // Keep quoted values intact: operator words and subject. can be literal data.
        $quotedStrings = <<<'REGEX'
/('[^'\\]*(?:\\.[^'\\]*)*'|"[^"\\]*(?:\\.[^"\\]*)*")/s
REGEX;
        $parts = preg_split($quotedStrings, $guard, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $index => &$part) {
            if (1 === $index % 2) {
                continue;
            }
            $part = preg_replace('/\bsubject\./', '', $part);
            $part = preg_replace('/\bnot\s+(?!in\b)/', '!', $part);
            $part = preg_replace('/\band\b/', '&&', $part);
            $part = preg_replace('/\bor\b/', '||', $part);
            $part = preg_replace('/\s+/', ' ', $part);
            $part = preg_replace('/\s*(===|!==|==|!=|<=|>=|<|>)\s*/', '$1', $part);
            $part = preg_replace('/\s*(&&|\|\|)\s*/', "\n$1 ", $part);
        }
        unset($part);
        $compact = (null !== $guardLabel && '' !== trim($guardLabel)
            ? wordwrap(trim($guardLabel), 32, "\n")
            : trim(implode('', $parts)));

        return '<'.$title.'<BR/><FONT POINT-SIZE="9" COLOR="#64748b"><I>'
            .str_replace("\n", '<BR ALIGN="LEFT"/>', $html($compact)).'</I></FONT>>';
    }

    protected function escape(string|bool $value): string
    {
        return \is_bool($value) ? ($value ? '1' : '0') : addslashes($value);
    }

    /**
     * Collapse whitespace/newlines so a multi-line description renders as a single clean tooltip.
     */
    protected function normalizeTooltip(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    protected function addAttributes(array $attributes): string
    {
        $code = [];

        foreach ($attributes as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $this->escape($v));
        }

        return $code ? ' '.implode(' ', $code) : '';
    }
}
