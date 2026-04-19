<?php
session_start();
session_destroy();
header("Location: /workspace_pro/auth/login.php");
exit();
?>
