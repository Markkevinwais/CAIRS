<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

unset($_SESSION['user']);
session_regenerate_id(true);
setFlash('You have been logged out.');
redirectTo('login.php');
