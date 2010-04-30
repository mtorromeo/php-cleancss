<?php
require_once('simpletest/autorun.php');
require_once('../cleancss.php');

class CleanCSSTestCase extends UnitTestCase {
	function testConvert() {
		$this->assertEqual( CleanCSS::convertString(
"#header, #footer:
	margin: 0
	padding: 0
	font->
		family: Verdana, sans-serif
		size: .9em

	li:
		padding: 0.4em
		margin: 0.8em 0 0.8em
		
		a:
			background-image: url('abc.png')
			&:hover:
				background-color: red

		h3:
			font-size: 1.2em
		p, div.p:
			padding: 0.3em
		p.meta:
			text-align: right
			color: #ddd"),
"#header,
#footer {
	margin: 0;
	padding: 0;
	font-family: Verdana, sans-serif;
	font-size: .9em;
}
#header li,
#footer li {
	padding: 0.4em;
	margin: 0.8em 0 0.8em;
}
#header li a,
#footer li a {
	background-image: url('abc.png');
}
#header li a:hover,
#footer li a:hover {
	background-color: red;
}
#header li h3,
#footer li h3 {
	font-size: 1.2em;
}
#header li p,
#header li div.p,
#footer li p,
#footer li div.p {
	padding: 0.3em;
}
#header li p.meta,
#footer li p.meta {
	text-align: right;
	color: #ddd;
}
");
	}
}