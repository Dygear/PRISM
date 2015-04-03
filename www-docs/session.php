<?php
if (isset($SERVER['PHP_AUTH_USER'])) {
    if (!isset($_SESSION)) {
        $_SESSION = array('user' => $SERVER['PHP_AUTH_USER'], 'counter' => 0); 
    }
    else {
        $_SESSION['user'] = $SERVER['PHP_AUTH_USER']; 
    }
    $_SESSION['autoLogin'] = true;
}
else
{
    if (isset($_SESSION['autoLogin'])) { unset($_SESSION['autoLogin']); 
    }
}
?>