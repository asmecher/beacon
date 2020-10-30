<?php

require_once('Entity.inc.php');

class CountSpans extends Entity {
	/**
	 * @copydoc Entity::getTableName()
	 */
	protected function getTableName() {
		return 'count_spans';
	}
}
