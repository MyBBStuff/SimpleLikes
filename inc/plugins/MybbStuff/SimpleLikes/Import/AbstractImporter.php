<?php

/**
 * Base importer class to be extended by all custom importers.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.4.0
 */
abstract class MybbStuff_SimpleLikes_Import_AbstractImporter
{
    /**
     * @var \DB_MySQLi $db
     */
    protected $db;

    public function __construct(\DB_MySQLi $db)
    {
        $this->db = $db;
    }

    /**
     * Get the title of the importer.
     *
     * @return string The title of the importer.
     */
    public abstract function getTitle();

    /**
     * Get the description for the importer.
     *
     * @return string A short description of the importer.
     */
    public abstract function getDescription();

    /**
     * Perform the conversion of the likes from the 3rd party system.
     *
     * @return int The number of converted likes.
     */
    public abstract function importLikes();
}
