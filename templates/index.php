<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\NotMiro\AppInfo\Application::APP_ID, OCA\NotMiro\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\NotMiro\AppInfo\Application::APP_ID, OCA\NotMiro\AppInfo\Application::APP_ID . '-main');

?>

<div id="notmiro"></div>
