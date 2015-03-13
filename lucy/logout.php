<?php

session_start();

// Kill the session
unset($_SESSION['lucy_id']);
unset($_SESSION['lucy_token']);
session_destroy(); // destroy fb data

// Kill the cookies
setcookie('lucy_id', '', time() - 3600, '/');
setcookie('lucy_token', '', time() - 3600, '/');

// Redirect to the dashboard
header("Location: index.php");
