<?php

require_once('config.php');
require_once('converter.php');

$converter = new Converter;

if (!isset($_POST['convert']))
{
    echo '<form method="POST"><input type="submit" name="convert" value="Import orders and members" /></form>';
}
else
{
    
    $result = $converter->run();
    
    echo $result;
}