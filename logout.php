<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

unset($_SESSION['uid']);
session_regenerate_id(true);
redirect('login.php');
