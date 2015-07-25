<?php
/**
 * SimpleLikes admin import page.
 *
 * Allows the importing of likes from other like systems.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 2.0.0
 */

// Disallow direct access to this file for security reasons
defined(
	'IN_MYBB'
) or die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');

if (!isset($lang->simplelikes)) {
	$lang->load('simplelikes');
}

$importManager = MybbStuff_SimpleLikes_Import_Manager::getInstance();

if (!isset($mybb->input['id'])) {
	$page->add_breadcrumb_item($lang->simplelikes, 'index.php?module=mybbstuff_likes');
	$page->add_breadcrumb_item($lang->simplelikes_import, 'index.php?module=mybbstuff_likes-import');
	$page->output_header($lang->simplelikes_import);

	$table = new Table();

	$table->construct_header($lang->simplelikes_importer_title);
	$table->construct_header($lang->simplelikes_importer_description);
	$table->construct_header($lang->simplelikes_importer_actions, ['class' => 'align_center']);

	foreach ($importManager->getImporters() as $id => $importer) {
		$table->construct_cell($importer->getTitle(), ['style' => 'width: 25%']);
		$table->construct_cell($importer->getDescription(), ['style' => 'width: 50%']);
		$table->construct_cell(
			'<a href="index.php?module=mybbstuff_likes&amp;id=' . (int)$id . '">Run Importer</a>',
			['style' => 'width: 25%', 'class' => 'align_center']
		);
		$table->construct_row();
	}

	$table->output($lang->simplelikes_import);

	$page->output_footer();
} else {
	$importerId = (int)$mybb->input['id'];
	$importers = $importManager->getImporters();

	if (!isset($importers[$importerId])) {
		die('Invalid importer ID!');
	}

	/** @var MybbStuff\SimpleLikes\Import\AbstractImporter $importer */
	$importer = $importers[$importerId];

	try {
		$numImported = $importer->importLikes();

		flash_message($lang->sprintf($lang->simplelikes_import_success_count_imported, $numImported), 'success');
		admin_redirect('index.php?module=mybbstuff_likes-import');
	} catch (Exception $e) {
		flash_message($lang->sprintf($lang->simplelikes_import_error, $e->getMessage()), 'error');
		admin_redirect('index.php?module=mybbstuff_likes-import');
	}
}
