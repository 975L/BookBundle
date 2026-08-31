<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Service;

use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;

// What the four export actions of a catalog screen need, as one collaborator: a serie, a book and a strip ask here for a table dump or for the rows they checked, and none of the three has to carry the connection and the two exporters it takes to serve them (see Controller\Management\Trait\TrashableCrudTrait)
class BookCatalogExporter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContentExporter $contentExporter,
        private readonly TableExporter $tableExporter,
    ) {
    }

    // The whole table in one of the three formats, exactly as the screen's export group offers it
    public function exportTable(ExportFormat $format, string $table): Response
    {
        return $this->tableExporter->export($format, $table, $this->fetchRows($table));
    }

    // The checked rows as a re-importable zip, the files they point at travelling with them
    public function exportSelection(string $kind, array $items, array $files): Response
    {
        return $this->contentExporter->export($kind, $items, $files);
    }

    // Every row of the table, ordered by id so two dumps of the same data compare
    /** @return array<int, array<string, mixed>> */
    private function fetchRows(string $table): array
    {
        return $this->connection->fetchAllAssociative(sprintf('SELECT * FROM `%s` ORDER BY `id`', $table));
    }
}
