<?php

$host = 'localhost';
$dbname = 'library-management-system';
$dbuser = 'root';
$dbpass = '';


// Connect to MySQL server
$db = new PDO("mysql:host=$host;", $dbuser, $dbpass);


// Build database
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname`";
$db->exec($sql);

echo "Database build successfully<br>";


// Select database
$db->exec("USE `$dbname`");


// Build categories table
$sql = "CREATE TABLE IF NOT EXISTS categories(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    image VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$db->exec($sql);
echo "Categories table created successfully<br>";

//Build books table
$sql = "CREATE TABLE IF NOT EXISTS books(
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(150) NOT NULL,
      author VARCHAR(100) NOT NULL,
      genre VARCHAR(80) NOT NULL,
      image VARCHAR(255) NOT NULL,
      status VARCHAR(50) NOT NULL,
      stars VARCHAR(50) NOT NULL,
      notes LONGTEXT NOT NULL,
      created_at DATE NOT NULL,
      source LONGTEXT NOT NULL,
      user_id INT NOT NULL

)";
$db->exec($sql);
echo "Books table created successfully<br>";

//BUild users table
$sql = "CREATE TABLE IF NOT EXISTS users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL
)";
$db->exec($sql);
echo "Users table created successfully";

$db=null;


?>