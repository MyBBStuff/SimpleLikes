<?php

class MybbStuff_SimpleLikes_LikeFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
	/**
	 * Init function called before running formatAlert(). Used to load language
	 * files and initialize other required resources.
	 *
	 * @return void
	 */
	public function init()
	{
		if (!isset($this->lang->simplelikes)) {
			$this->lang->load('simplelikes');
		}

		$this->alertTypeName = 'simplelikes';
	}

	/**
	 * Format an alert into it's output string to be used in both the main
	 * alerts listing page and the popup.
	 *
	 * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	 * @param array $outputAlert The alert output
	 *                                                     details, including
	 *                                                     formated from user
	 *                                                     name, from user
	 *                                                     profile link and
	 *                                                     more.
	 *
	 * @return string The formatted alert string.
	 */
	public function formatAlert(
		MybbStuff_MyAlerts_Entity_Alert $alert,
		array $outputAlert
	) {
		return $this->lang->sprintf(
			$this->lang->simplelikes_alert,
			$outputAlert['from_user_profilelink'],
			$this->buildShowLink($alert)
		);
	}

	/**
	 * Build a link to an alert's content so that the system can redirect to
	 * it.
	 *
	 * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the
	 *                                               link for.
	 *
	 * @return string The built alert, preferably an absolute link.
	 */
	public function buildShowLink(
		MybbStuff_MyAlerts_Entity_Alert $alert
	) {
		$extraContent = $alert->getExtraDetails();
		$tid = -1;

		if (isset($extraContent['tid'])) {
			$tid = (int)$extraContent['tid'];
		}

		return get_post_link($alert->getObjectId(), $tid) . '#pid' . $alert->getObjectId();
	}
}