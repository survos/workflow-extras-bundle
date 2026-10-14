<?php

declare(strict_types=1);

namespace Survos\WorkflowExtrasBundle\Tests;

use PHPUnit\Framework\TestCase;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowExtrasBundle\Attribute\Transition;
use Survos\WorkflowExtrasBundle\Twig\WorkflowIconExtension;

final class IconTest extends TestCase
{
    public function testIconsAreSharedMetadata(): void
    {
        foreach ([new Place(icon: 'tabler:check', iconClass: 'text-success'), new Transition('a', 'b', icon: 'tabler:check', iconClass: 'text-success')] as $attribute) {
            self::assertSame('tabler:check', $attribute->metadata['icon']);
            self::assertSame('text-success', $attribute->metadata['iconClass']);
        }
    }

    public function testConflictingMetadataIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Place(icon: 'tabler:check', metadata: ['icon' => 'tabler:x']);
    }

    public function testMissingRendererOmitsIcon(): void
    {
        if (!class_exists(\Twig\Extension\AbstractExtension::class)) {
            self::markTestSkipped('Twig is optional.');
        }
        self::assertSame('', (new WorkflowIconExtension())->render('tabler:check'));
    }
}
