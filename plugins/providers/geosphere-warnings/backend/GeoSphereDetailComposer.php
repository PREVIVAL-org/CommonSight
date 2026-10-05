<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Model\Item\SectionHeading;
use CommonSight\Plugin\GeoSphere\Record\DetailWarning;
use CommonSight\Plugin\GeoSphere\Record\WarningDetail;
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Assembles the detail text of a GeoSphere warning from its sections (Q-W-AT-08). */
final class GeoSphereDetailComposer
{
    public function __construct(private readonly WarningSections $sections, private readonly UtcTimeParser $time) {}

    /** @return WarningDetail|null null if the message contains no text */
    public function compose(DetailWarning $warning): ?WarningDetail
    {
        $sections = $this->sections->of([
            SectionHeading::Description->value => $warning->text,
            SectionHeading::Situation->value => $warning->meteotext,
            SectionHeading::Impact->value => $warning->auswirkungen,
            SectionHeading::Advice->value => $warning->empfehlungen,
            SectionHeading::Update->value => $warning->updategrund,
        ]);

        return $sections === [] ? null : new WarningDetail($sections, $this->time->parseUtc($warning->create));
    }
}
