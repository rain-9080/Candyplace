<?php
// Configurações do Banco de Dados
$host = "localhost";
$usuario = "root"; // Mude para seu usuário
$senha = "";     // Mude para sua senha
$banco = "CandyPlace";

// Cria a conexão
$mysqli = new mysqli($host, $usuario, $senha, $banco);

// Verifica a conexão
if ($mysqli->connect_error) {
    die("Falha na conexão com o banco de dados: " . $mysqli->connect_error);
}

// Define o charset para evitar problemas com acentuação
$mysqli->set_charset("utf8mb4");
?>