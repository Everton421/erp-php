<?php
require_once __DIR__ . '/config/config.php';

if (usuario_atual()) {
    ir_para_inicial();
}
redirecionar('login/index.php');