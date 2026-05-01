<?php
function redirect($path) {
  header("Location: $path");
  exit;
}

function is_logged_in() { 
  return !empty($_SESSION['user']); 
}

function require_login() {
  if (!is_logged_in()) {
    header("Location: /jobportalsystem/auth/landing.php");
    exit;
  }
}
