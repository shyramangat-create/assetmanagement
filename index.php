<?php
require_once __DIR__ . '/config/config.php';
redirect(!empty($_SESSION['admin_id']) ? 'dashboard.php' : 'login.php');
