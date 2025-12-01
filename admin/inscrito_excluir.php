<?php
require_once '../bd/config.php';
$id = (int)$_GET['id'];
$pdo->prepare("DELETE FROM tb_inscritos WHERE id = ?")->execute([$id]);
header("Location: inscritos.php");
?>