<?php
  $host = 'localhost';
  $dbname = 'library-management-system';
  $dbuser = 'root';
  $dbpass = '';

  $db = new PDO("mysql:host=$host;dbname=$dbname",$dbuser,$dbpass);

  if(!$db){
    echo "database connected fail";
  }
?>