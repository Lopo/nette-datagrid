<?php

namespace DataGrid\Columns;

use Nette\Utils;

/**
 * Representation of textual data grid column.
 *
 * @author     Roman Sklenář
 * @copyright  Copyright (c) 2009 Roman Sklenář (http://romansklenar.cz)
 * @license    New BSD License
 * @example    http://addons.nette.org/datagrid
 * @package    Nette\Extras\DataGrid
 */
class TextColumn
extends HtmlColumn
{
	/**
	 * Formats cell's content.
	 * @param mixed $value
	 * @param \DibiRow|array $data
	 * @return string
	 */
	public function formatContent($value, $data = NULL)
	{
		return parent::formatContent(htmlSpecialChars($value), $data);
	}

	/**
	 * Filters data source.
	 * @param mixed
	 */
	public function applyFilter($value)
	{
		if (!$this->hasFilter()) {
			return;
		}

		$dataSource = $this->getDataGrid()->getDataSource();

		if (strpos($value, '*') !== FALSE) {
			$dataSource->filter($this->name, 'LIKE', $value); //asterisks are converted internally
		} elseif ($value === 'NULL' || $value === 'NOT NULL') {
			$dataSource->filter($this->name, "IS $value");
		} else {
			$dataSource->filter($this->name, 'LIKE', "*$value*");
		}
	}
}
