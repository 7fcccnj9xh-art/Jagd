<?php
require_once __DIR__ . '/shared/config/config.php';
require_once __DIR__ . '/shared/auth/auth.php';

sessionStart();
sessionDestroy();

header('Location: ' . BASE_URL . 'login.php');
exit;
