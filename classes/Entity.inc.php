<?php

require_once('BeaconDatabase.inc.php');

abstract class Entity {
	protected $_db;

	function __construct(BeaconDatabase $db) {
		$this->_db = $db;
	}

	/**
	 * Find a single entry by its specified characteristics.
	 * @param $characteristics array [column_name => value, ...]
	 * @return array|null Context characteristics, or null if not found.
	 */
	public function find(array $characteristics) {
		$query = $this->_db->getCapsule()->table($this->getTableName());
		foreach ($characteristics as $name => $value) $query->where($name, '=', $value);
		return (array) $query->get()->first();
	}

	/**
	 * Add a new entry.
	 * @param $entry array
	 * @return array New entry.
	 */
	public function insert($entry) {
		$this->_db->getCapsule()->table($this->getTableName())->insert($entry);
		return $entry;
	}

	/**
	 * Update fields in an entry.
	 * @param $id int The ID of the entry to update
	 * @param $fields array Data to include in the entry
	 */
	public function update(int $id, array $fields) {
		$this->_db->getCapsule()->table($this->getTableName())
			->where('id', '=', $id)
			->update($fields);
	}

	/**
	 * Get the name of the database table for this entity.
	 */
	abstract protected function getTableName();
}

