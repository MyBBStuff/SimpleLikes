<?php
declare(strict_types=1);

namespace MybbStuff\SimpleLikes\Import;

/**
 * Base importer class to be extended by all custom importers.
 */
abstract class AbstractImporter
{
    /**
     * @var \DB_Base $db
     */
    protected $db;

    public function __construct(\DB_Base $db)
    {
        $this->db = $db;
    }

    /**
     * Get the title of the importer.
     *
     * @return string The title of the importer.
     */
    public abstract function getTitle(): string;

    /**
     * Get the description for the importer.
     *
     * @return string A short description of the importer.
     */
    public abstract function getDescription(): string;

    /**
     * Perform the conversion of the likes from the 3rd party system.
     *
     * @return int The number of converted likes.
     */
    public abstract function importLikes(): int;
}
