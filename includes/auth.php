<?php
require_once dirname(__DIR__) . '/config/config.php';
if (empty($_SESSION['admin_id'])) {
    redirect('login.php');
}
