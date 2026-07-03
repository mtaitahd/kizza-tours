<?php
// KIZZA TOURS & SAFARIS - Admin Logout
session_start();
session_destroy();
header('Location: ./');
exit;
