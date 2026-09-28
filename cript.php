<?php


         $ini= parse_ini_file( 'config.ini', true);
 
 
             if ($ini === false) {
                 print('Erro ao ler arquivo ini.');
             }
         $dataini = $ini['config'];

         $salt = $dataini['salt'];


$token = $salt;
$password =1234;
$hash = crypt( $token, $password );
print($hash);


?>