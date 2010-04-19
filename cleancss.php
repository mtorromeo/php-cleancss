<?php
class CleanCSS_ParserException extends Exception {}

class CleanCSS {
	protected $source;
	const version = '1.0';

	public function __construct($file) {
		$this->source = file_get_contents($file);
	}
	
	protected function flattenSelectors($selectorTree) {
		$tail = array_pop($selectorTree);
		if (count($selectorTree)>0) {
			$flattenedBase = $this->flattenSelectors($selectorTree);
			if ($pp) echo "-- $flattenedBase\n";
			foreach ($tail as $i => $sel) {
				if ($sel[0] == '&')
					$sel = substr($sel,1);
				elseif ($flattenedBase != '')
					$sel = ' '.$sel;
				$tail[$i] = $flattenedBase.$sel;
			}
		}
		return implode(",\n", $tail);
	}
	
	public function toCss() {
		$level = 0;
		$indenter = 0;
		$selectorsChanged = False;
		$rules = array();
		$cur_rule_tree = array();
		$rule_prefixes = array();
	
		foreach (explode("\n", $this->source) as $lineno => $line) {
			if (trim($line) == '') continue;
			
			preg_match('/^\s*/', $line, $matches);
			$indentation = $matches[0];
			if ($indenter == 0 && strlen($indentation)>0)
				$indenter = strlen($indentation);
			
			if ($indenter>0 && strlen($indentation) % $indenter != 0)
				throw new CleanCSS_ParserException("Indentation error. Line: $lineno.");
			
			$newlevel = $indenter > 0 ? strlen($indentation) / $indenter : 0;
			$line = trim($line);
			
			if ($newlevel-$level>1)
				throw new CleanCSS_ParserException("Indentation error. Line: $lineno.");

			# Pop to new level
			while (count($cur_rule_tree)+count($rule_prefixes)>$newlevel && count($rule_prefixes)>0)
				array_pop($rule_prefixes);
			while (count($cur_rule_tree)>$newlevel)
				array_pop($cur_rule_tree);
			$level = $newlevel;
			
			if (preg_match('/^(.+)\s*:$/', $line, $matches)) {
				$selectors = explode(',', $matches[1]);
				foreach ($selectors as $i => $sel)
					$selectors[$i] = trim($sel);
				$cur_rule_tree[] = $selectors;
				$selectorsChanged = True;
				continue;
			}
			
			if (preg_match('/^([^:>\s]+)->$/', $line, $matches)) {
				$rule_prefixes[] = $matches[1];
				continue;
			}
			
			if (preg_match('/^([^\s]+)\s*:\s*(.+)$/', $line, $matches)) {
				if (count($cur_rule_tree) == 0)
					throw new CleanCSS_ParserException("Selector expected, found definition. Line: $lineno.");
				if ($selectorsChanged) {
					$selectors = $this->flattenSelectors($cur_rule_tree);
					$rules[] = array($selectors, array());
					$selectorsChanged = False;
				}
				if (count($rule_prefixes)>0)
					$prefixes = implode('-', $rule_prefixes) . '-';
				else
					$prefixes = '';
				$rules[count($rules)-1][1][] = $prefixes . $matches[1] . ': ' . $matches[2] . ';';
				continue;
			}

			throw new CleanCSS_ParserException("Unexpected item. Line: $lineno.");
		}
		
		$result = array();
		foreach ($rules as $rule)
			$result[] = $rule[0] . " {\n\t" . implode("\n\t", $rule[1]) . "\n}\n";
		return implode('', $result);
	}

	public static function convert($file) {
		$ccss = new CleanCSS($file);
		return $ccss->toCss();
	}

}