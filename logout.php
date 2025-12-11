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

// Finalmente, Destroi a sessão tanto de loja quanto para cliente
session_destroy();

// Redireciona para a página principal 
header("Location: index.php");
exit();
?>