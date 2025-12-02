<?php
session_start();

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Se necessário, destrói o cookie de sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão
session_destroy();

// Redireciona para a página principal (agora o index.php saberá que ele está deslogado)
header("Location: index.php");
exit();
?>