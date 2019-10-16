<?php
include "calc.php";
$calc = calc::create();
$calc->addFunc("stephin", function() {
	return rand(1,10);
});
echo $calc->calc('stephin() + 1+2*3/4');
