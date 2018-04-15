<?php
namespace P0n0marev\BitrixMigrations\Interfaces;

/**
 * Field interface
 */
interface FieldInterface
{
	public function equals(FieldInterface $field);

	public function setTableName($tableName);

	public function getTableName();

	public function setName($name);

	public function getName();

	public function setType($type);

	public function getType();
}