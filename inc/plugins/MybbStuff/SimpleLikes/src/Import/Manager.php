<?php

/**
 * Importer manager class that allows the registering of custom importers extending the AbstractImporter class.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 2.0.0
 */
class MybbStuff_SimpleLikes_Import_Manager
{
    /**
     * Singleton instance.
     *
     * @var MybbStuff_SimpleLikes_Import_Manager $instance
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

    private function __construct(\DB_Base $db)
    {
        $this->db = $db;
        $this->importers = [];
    }

    /**
     * Get an instance of the import manager.
     *
     * @return MybbStuff_SimpleLikes_Import_Manager The singleton instance.
     */
    public static function getInstance()
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
    public function addImporter($importerClass = '')
    {
        $importerClass = (string)$importerClass;

        if (class_exists($importerClass)) {
            $instance = new $importerClass($this->db);
            if ($instance instanceof MybbStuff_SimpleLikes_Import_AbstractImporter) {
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
     * @return MybbStuff_SimpleLikes_Import_AbstractImporter[]
     */
    public function getImporters()
    {
        $importers = [];

        foreach ($this->importers as $importer) {
            $importers[] = new $importer($this->db);
        }

        return $importers;
    }
}
