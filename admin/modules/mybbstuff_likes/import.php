<?php
/**
 * SimpleLikes admin import page.
 *
 * Allows the importing of likes from other like systems.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.4.0
 */

// Disallow direct access to this file for security reasons
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!$lang->simplelikes) {
    $lang->load('simplelikes');
}

$importManager = MybbStuff\SimpleLikes\Import\Manager::getInstance();

if (!isset($mybb->input['id'])) {
    $page->add_breadcrumb_item($lang->simplelikes, 'index.php?module=mybbstuff_likes');
    $page->add_breadcrumb_item($lang->simplelikes_import, 'index.php?module=mybbstuff_likes-import');
    $page->output_header($lang->simplelikes_import);

    $table = new Table;

    $table->construct_header($lang->simplelikes_importer_title);
    $table->construct_header($lang->simplelikes_importer_description);
    $table->construct_header($lang->simplelikes_importer_actions, array('class' => 'align_center'));

    /** @var MybbStuff\SimpleLikes\Import\AbstractImporter $importer */
    foreach ($importManager->getImporters() as $id => $importer) {
        $table->construct_cell($importer->getTitle(), array('style' => 'width: 25%'));
        $table->construct_cell($importer->getDescription(), array('style' => 'width: 50%'));
        $table->construct_cell('<a href="index.php?module=mybbstuff_likes&amp;id=' . (int) $id .'">Run Importer</a>', array('style' => 'width: 25%', 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->simplelikes_import);

    $page->output_footer();
} else {
    $importerId = (int) $mybb->input['id'];
    $importers = $importManager->getImporters();

    if (!isset($importers[$importerId])) {
        die('Invalid importer ID!');
    }

    $importer = $importers[$importerId];

    var_dump($importer);
}


