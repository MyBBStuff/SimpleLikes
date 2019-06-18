<?php
declare(strict_types=1);

namespace MybbStuff\SimpleLikes\Import;

use DB_Base;

/**
 * Importer manager class that allows the registering of custom importers extending the AbstractImporter class.
 */
class Manager
{
    /**
     * Singleton instance.
     *
     * @var self $instance
     */
    private static $instance;

    /**
     * @var DB_Base $db
     */
    private $db;

    /**
     * @var array $importers
     */
    private $importers;

    private function __construct(DB_Base $db)
    {
        $this->db = $db;
        $this->importers = [];
    }

    /**
     * Get an instance of the import manager.
     *
     * @return Manager The singleton instance.
     */
    public static function getInstance(): self
    {
        if (!static::$instance) {
            global $db;

            static::$instance = new static($db);
        }

        return static::$instance;
    }

    /**
     * Add an importer to the manager.
     *
     * @param string $importerClass The importer class to be added.
     *
     * @throws \InvalidArgumentException Thrown if $importerClass does not exist or doesn't extend AbstractImporter.
     */
    public function addImporter(string $importerClass = ''): void
    {
        $importerClass = (string)$importerClass;

        if (class_exists($importerClass)) {
            $instance = new $importerClass($this->db);
            if ($instance instanceof AbstractImporter) {
                $this->importers[] = $importerClass;

                return;
            }
        }

        throw new \InvalidArgumentException(
            '$importerClass should be a valid class name that extends AbstractImporter'
        );
    }

    /**
     * Get all of the registered importers.
     *
     * @return AbstractImporter[]
     */
    public function getImporters(): array
    {
        $importers = [];

        foreach ($this->importers as $importer) {
            $importers[] = new $importer($this->db);
        }

        return $importers;
    }
}
