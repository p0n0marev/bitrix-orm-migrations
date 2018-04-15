<?php
namespace P0n0marev\BitrixMigrations\Lib;

use P0n0marev\BitrixMigrations\Interfaces\FieldInterface;

class Field implements FieldInterface
{
	/** @var string имя таблицы */
	private $tableName;
	/** @var string имя поля */
	private $name;
	/** @var string тип данных */
	private $type;

	public function equals(FieldInterface $field)
	{
		if ($this->getName() != $field->getName()) return false;

		// если поле в базе помечено как blob не меняем его тип
		if ($this->getType() == 'blob' && strpos($field->getType(), 'varchar') !== false) return true;

		return true;
	}

	/**
	 * @return string
	 */
	public function getTableName()
	{
		return $this->tableName;
	}

	/**
	 * @param string tableName
	 */
	public function setTableName($tableName)
	{
		$this->tableName = $tableName;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 */
	public function setType($type)
	{
		$this->type = $type;
	}

	/**
	 * Запрос на удаление поля
	 * @return string
	 */
	public function getDropQuery()
	{
		$query = 'ALTER TABLE `' . $this->getTableName() . '` DROP COLUMN ';
		$query .= '`' . $this->getName() . '`;';

		return $query;
	}


	/**
	 * вернет команду для добавления поля
	 * @return string
	 */
	public function getAddQuery()
	{
		$query = 'ALTER TABLE `' . $this->getTableName() . '` ADD ';
		$query .= '`' . $this->getName() . '` ';
		$query .= " " . $this->getType() . " ";

		return $query;
	}


	/**
	 * Запрос на переименование поля
	 * @param FieldInterface $field
	 * @return string
	 */
	public function getChangeQuery(FieldInterface $field)
	{
		$query = 'ALTER TABLE `' . $this->getTableName() . '` CHANGE ';
		$query .= ' `' . $this->getName() . '` ';
		$query .= ' `' . $field->getName() . '` ';
		$query .= " " . $field->getType() . " ";

		return $query;
	}


	/**
	 * вернет команду для изменения поля
	 * @param FieldInterface $field
	 * @return string
	 */
	public function getModifyQuery($field)
	{
		$query = 'ALTER TABLE `' . $this->getTableName() . '` MODIFY ';
		$query .= ' `' . $field->getName() . '` ';

		if ($this->getType() == 'blob') { // если поле в базе помечено как blob не меняем его тип
			$query .= " blob ";
		} else {
			$query .= " " . $field->getType() . " ";
		}

		return $query;
	}

}