#!/usr/bin/php -q
<?php
require '../core_project/result_to_chart/create_html_chart.php';
date_default_timezone_set('Europe/Rome');
error_reporting(E_ERROR | E_WARNING | E_PARSE);

if ($argc != 2){
    die("Numero paramatri errato -- Inserire soltanto il file dei dati di cui si vuole il grafico\n");
}

$file = $argv[1];

$root_dirPath = "/home/federico/Scrivania/progetto_tesi/core_project/result_to_chart/dati_tmp";

if(!file_exists($root_dirPath))
    mkdir($root_dirPath);
else if(file_exists($root_dirPath."/data_to_chart"))
    unlink($root_dirPath."/data_to_chart");

if (is_file($file))
    copy($file,$root_dirPath."/data_to_chart");
else
    die("Necessario inserire un file come input per il grafico.\n");

/*il main mi crea il file html con il grafico relativo ai risultati passati*/
main();

exec("google-chrome ".$root_dirPath."/index_chart.html");

?>
