<?php
// Stand-in for Divi's et-pagebuilder/builder-5.php: reports $GLOBALS['divi5_enabled'].
function et_builder_d5_enabled(): bool {
	return (bool) $GLOBALS['divi5_enabled'];
}
