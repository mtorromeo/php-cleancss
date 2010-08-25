<?php
class CleanCSS_ParserException extends Exception {}

class CleanCSS {
	protected $source;
	protected $callbacks = array();
	const version = '1.4';

	public function __construct($file, $source=null) {
		if (is_null($source))
			$this->source = file_get_contents($file);
		else
			$this->source = $source;
	}

	protected function flattenSelectors($selectorTree) {
		$selectors = array();
		$base = array_shift($selectorTree);
		$tails = null;
		if (count($selectorTree)>0)
			$tails = $this->flattenSelectors($selectorTree);
		foreach($base as $i => $sel) {
			if (!is_null($tails)) {
				foreach($tails as $tail) {
					if ($tail[0] == '&')
						$tail = substr($tail,1);
					else
						$tail = " $tail";
					$selectors[] = $sel.$tail;
				}
			} else {
				$selectors[] = $sel;
			}
		}
		return $selectors;
	}

	public function toCss() {
		$level = 0;
		$indenter = 0;
		$selectorsChanged = False;
		$rules = array();
		$cur_rule_tree = array();
		$rule_prefixes = array();

		foreach (explode("\n", $this->source) as $lineno => $line) {
			$line = preg_replace('|//.*$|', '', $line);

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
					$selectors = implode(",\n", $this->flattenSelectors($cur_rule_tree));
					$rules[] = array($selectors, array());
					$selectorsChanged = False;
				}
				if (count($rule_prefixes)>0)
					$prefixes = implode('-', $rule_prefixes) . '-';
				else
					$prefixes = '';

				if (count($this->callbacks)) {
					$properties = array();
					foreach($this->callbacks as $callback)
						$properties = array_merge($properties, call_user_func($callback, $matches[1], $matches[2]));
				} else {
					$properties = array(array($matches[1], $matches[2]));
				}

				foreach($properties as $property)
					$rules[count($rules)-1][1][] = $prefixes . trim($property[0]) . ': ' . trim($property[1]) . ';';

				continue;
			}

			throw new CleanCSS_ParserException("Unexpected item. Line: $lineno.");
		}

		$result = array();
		foreach ($rules as $rule)
			$result[] = $rule[0] . " {\n\t" . implode("\n\t", $rule[1]) . "\n}\n";
		return implode('', $result);
	}

	public function registerPropertyCallback($callback) {
		if (is_callable($callback))
			$this->callbacks[] = $callback;
	}

	public static function convert($file, $propertyCallback=null) {
		$ccss = new CleanCSS($file);
		$ccss->registerPropertyCallback($propertyCallback);
		return $ccss->toCss();
	}

	public static function convertString($source, $propertyCallback=null) {
		$ccss = new CleanCSS(null, $source);
		$ccss->registerPropertyCallback($propertyCallback);
		return $ccss->toCss();
	}

	public static function output($file, $propertyCallback=null) {
		header('Content-Type: text/css');
		echo CleanCSS::convert($file, $propertyCallback);
	}

}