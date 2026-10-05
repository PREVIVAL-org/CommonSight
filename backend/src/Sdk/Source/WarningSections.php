<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Item\SectionHeading;
use CommonSight\Model\Item\WarningSection;

/** Builds from texts per section kind the list of non-empty warning text sections in a fixed order. */
final class WarningSections
{
    /**
     * @param array<string, string> $texts section kind (SectionHeading value) -> text
     * @return list<WarningSection>
     */
    public function of(array $texts): array
    {
        $sections = [];
        foreach (SectionHeading::cases() as $heading) {
            $text = trim($texts[$heading->value] ?? '');
            if ($text !== '') {
                $sections[] = new WarningSection($heading, $text);
            }
        }

        return $sections;
    }
}
