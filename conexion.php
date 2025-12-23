<?php
    //$servername = 'b88e0bd2df17.sn.mynetname.net:3306';
    $servername = '192.168.99.253:3306';
   //$database = "bbsnetwo_Datos-clientes";
    $database = 'sysbbs';
    $username = 'adminbbs';
    $password = 'Admin_Pinck';
    // Create connection
    $conexion = mysqli_connect($servername, $username, $password, $database);
    mysqli_set_charset($conexion, 'utf8'); //linea a colocar
    // Check connection
    if (!$conexion) {
        die("Connection failed: " . mysqli_connect_error());
    }
    // else{
        // echo "conexion exitosa";
    // }
?>