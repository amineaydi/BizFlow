<?php
session_start();
require_once 'lang.php';
echo "Direction: " . getLangDir() . "<br>";
echo "Save in current lang: " . __('save') . "<br>";
echo renderLangSwitcher();
?>
