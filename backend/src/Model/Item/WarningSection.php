<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/** A section of the warning text in the original language. */
final readonly class WarningSection implements \JsonSerializable
{
    public function __construct(public SectionHeading $heading, public string $text)
    {
        if ($text === '') {
            throw new \InvalidArgumentException('Leerer Abschnitt');
        }
    }

    /** @return array{heading: string, text: string} */
    public function jsonSerialize(): array
    {
        return ['heading' => $this->heading->value, 'text' => $this->text];
    }
}
