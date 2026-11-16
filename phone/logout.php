<?php declare(strict_types=1);require __DIR__.'/app/core.php';start_portal_session();$_SESSION=[];session_destroy();header('Location: login.php');exit;
