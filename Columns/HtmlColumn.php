<?php

namespace DataGrid\Columns;

use Nette\Utils;

class HtmlColumn
extends Column
{
	
	public function formatContent($value, $data = NULL)
	{

		if (is_array($this->replacement) && !empty($this->replacement)) {
			if (in_array($value, array_keys($this->replacement))) {
				$value = $this->replacement[$value];
			}
		}

		foreach ($this->formatCallback as $callback) {
			if (is_callable($callback)) {
				$value = call_user_func($callback, $value, $data);
			}
		}

		// truncate
		if ($this->maxLength != 0) {
			if ($value instanceof Utils\Html) {
				$text = $value->getText();
				$text = Utils\Strings::truncate($text, $this->maxLength);
				$value->setText($text);
			} else {
				$value = Utils\Strings::truncate($value, $this->maxLength);
			}
		}

		return $value;
	}

}
