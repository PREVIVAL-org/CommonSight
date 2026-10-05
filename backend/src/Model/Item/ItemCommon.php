<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

use CommonSight\Model\Geometry;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\UtcInstant;

/** Common fields of all kinds of items (D-10). */
final readonly class ItemCommon
{
    /** @param list<RegionId> $regionIds regions determined by the fetcher or by the source */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public ?string $source = null,
        public ?UtcInstant $time = null,
        public ?Coordinate $position = null,
        public ?Geometry $geometry = null,
        public array $regionIds = [],
        public RegionMatch $regionMatch = RegionMatch::None,
        public ?string $lang = null,
        /** only in the border zone: foreign country (ISO 3166-1 alpha-2) */
        public ?string $country = null,
        public ?Nearby $near = null,
    ) {
        if ($id === '') {
            throw new \InvalidArgumentException('Item without ID');
        }
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new \InvalidArgumentException('Item without valid link: ' . $id);
        }
    }

    /** @param list<RegionId> $regionIds */
    public function withRegions(array $regionIds, RegionMatch $match): self
    {
        return new self($this->id, $this->title, $this->url, $this->source, $this->time, $this->position, $this->geometry, $regionIds, $match, $this->lang, $this->country, $this->near);
    }

    public function withGeometry(?Geometry $geometry): self
    {
        return new self($this->id, $this->title, $this->url, $this->source, $this->time, $this->position, $geometry, $this->regionIds, $this->regionMatch, $this->lang, $this->country, $this->near);
    }

    public function withNear(Nearby $near): self
    {
        return new self($this->id, $this->title, $this->url, $this->source, $this->time, $this->position, $this->geometry, $this->regionIds, $this->regionMatch, $this->lang, $this->country, $near);
    }

    /** @return array<string, mixed> fields in the order of the schema */
    public function toArray(): array
    {
        $data = ['id' => $this->id, 'title' => $this->title, 'url' => $this->url];
        if ($this->source !== null) {
            $data['source'] = $this->source;
        }
        if ($this->time !== null) {
            $data['time'] = $this->time;
        }
        if ($this->position !== null) {
            $data['lat'] = $this->position->lat;
            $data['lon'] = $this->position->lon;
        }
        if ($this->geometry !== null) {
            $data['geometry'] = $this->geometry;
        }
        $data['regionIds'] = array_map(static fn(RegionId $id): string => $id->value, $this->regionIds);
        $data['regionMatch'] = $this->regionMatch->value;
        if ($this->lang !== null) {
            $data['lang'] = $this->lang;
        }
        if ($this->country !== null) {
            $data['country'] = $this->country;
        }
        if ($this->near !== null) {
            $data['near'] = $this->near->toArray();
        }

        return $data;
    }
}
