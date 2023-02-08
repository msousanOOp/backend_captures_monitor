<?php

$pdo = new PDO("mysql:host=ec2-3-223-3-86.compute-1.amazonaws.com;port=3306;dbname=mysql", "ale_frontend", "al3#xc@%12ldz", array(
    PDO::ATTR_TIMEOUT => 5,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
));

$query = "SELECT * FROM alemonitor.user";
try {
    $stm = $pdo->prepare($query);
    $stm->execute();
    var_dump($stm->fetchAll());
} catch (PDOException $e) {
    var_dump($e->getMessage());
}
