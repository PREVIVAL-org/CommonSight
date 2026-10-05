<?php

declare(strict_types=1);

namespace CommonSight\Port;

/** Reads XML without DTD and entities and returns the elements with one name (F-09). */
interface XmlEntryReader
{
    /**
     * @param list<string> $localNames local element names, e.g. ['item', 'entry']
     * @throws MalformedXml
     */
    public function read(string $xml, array $localNames): XmlDocument;
}
