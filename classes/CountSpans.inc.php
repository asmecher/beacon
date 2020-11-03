<?php

require_once('Entity.inc.php');

class CountSpans extends Entity {
	/**
	 * @copydoc Entity::getTableName()
	 */
	protected function getTableName() {
		return 'count_spans';
	}

	static function getDefaultLabel() {
		$currentYear = date('Y');
		$currentMonth = date('n');
		return $currentMonth >= 6 ? $currentYear - 1 : $currentYear - 2;
	}
}
