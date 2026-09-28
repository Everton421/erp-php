<?php
require_once __DIR__ . '/config/config.php';

if (usuario_atual()) {
    redirecionar('dashboard/index.php');
}
redirecionar('login/index.php');