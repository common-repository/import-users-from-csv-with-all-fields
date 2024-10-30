<?php
class RCHReadCSV {
	const field_start 		= 0;
	const unquoted_field 	= 1;
	const quoted_field 		= 2;
	const found_quote 		= 3;
	const found_cr_q 		= 4;
	const found_cr 			= 5;
	private $file;
	private $sep;
	private $eof;
	private $nc;
	public function __construct($file_handle, $sep, $skip="") {
		$this->file = $file_handle;
		$this->sep 	= $sep;
		$this->nc 	= fgetc($this->file);
		// skip junk at start
		for ($i=0; $i < strlen($skip); $i++) {
			if ($this->nc !== $skip[$i])
				break;
			$this->nc = fgetc($this->file);
		}
		$this->eof = ($this->nc === FALSE);
	}

	private function rch_next_char() {
		$c = $this->nc;
		$this->nc 	= fgetc($this->file);
		$this->eof 	= ($this->nc === FALSE);
		return $c;
	}

	public function csv_row() {
		if ($this->eof)
			return NULL;

		$row=array();
		$field="";
		$state=self::field_start;

		while (1) {
			$char = $this->rch_next_char();

			if ($state == self::quoted_field) {
				if ($char === FALSE) {
					$row[]=$field;
					return $row;
				}
			} elseif ($char === FALSE || $char == "\n") {
				$row[] = $field;
				return $row;
			} elseif ($char == "\r") {
				$state = ($state == self::found_quote)? self::found_cr_q: self::found_cr;
				continue;
			} elseif ($char == $this->sep && 
				($state == self::field_start ||
				$state == self::found_quote ||
				$state == self::unquoted_field)) {
					$row[]=$field;
					$field="";
					$state=self::field_start;
					continue;
				}

			switch ($state) {

			case self::field_start:
				if ($char == '"')
					$state = self::quoted_field;
				else {
					$state = self::unquoted_field;
					$field .= $char;
				}
				break;

			case self::quoted_field:
				if ($char == '"')
					$state = self::found_quote;
				else
					$field .= $char;
				break;

			case self::unquoted_field:
				$field .= $char;
				break;

			case self::found_quote:
				$field .= $char;
				$state = self::quoted_field;
				break;

			case self::found_cr:
				$field .= "\r".$char;
				$state = self::unquoted_field;
				break;

			case self::found_cr_q:
				$field .= "\r".$char;
				$state = self::quoted_field;
				break;
			}
		}
	}
}
