#!/usr/bin/php -q
<?php
date_default_timezone_set('Europe/Rome');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require '../core_project/main_converter.php';
require '../core_project/main_analysis.php';

function rrmdir($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

if($argc==1){
   die ("\nCONVERSIONE\n".
         "# Conversione del set di articoli in input in un formato intermedio con/senza analisi dei titoli delle sezioni\n".
         "./init_commandLine.php 0 'input_path' 'output_path' [ -analisi_titoli ]\n\n".
         "ANALISI (gli articoli in input devono essere del formato intermedio)\n".
         "# Analisi degli indici di aggregazione\n".
         "./init_commandLine.php 1 'input_path' 'output_path' [ -verbose ]\n".
         "# Analisi dei riferimenti sulle fette di testo\n".
         "./init_commandLine.php 2 'input_path' 'output_path' 'numero_fette' [ -verbose ]\n".
         "# Analisi dei riferimenti sulle sezioni rilevanti\n".
         "./init_commandLine.php 3 'input_path' 'output_path' [ -verbose ]\n".
         "# Analisi dei riferimenti su insiemi di sezioni\n".
         "./init_commandLine.php 4 'input_path' 'output_path' 'numero_insiemi' [ -verbose ]\n".
         "# Analisi dei titoli frequenti nelle sezioni (percentuale di abbattimento standard è del 40 percento)\n".
         "./init_commandLine.php 5 'input_path' [ 'percentuale_presenza_abbattimento' ]\n\n");
}

/*se i parametri sono maggiori 3 oppure l'analisi da fare è la 5 e i parametri sono 3 allora tutto ok*/
if ($argc > 3 || ($argc==3 && $argv[1]==5)){
      $analisi_toDo = $argv[1];

      $relative_obsolute_path1 = substr($argv[2], 0, 1);
      $relative_obsolute_path2 = substr($argv[2], 0, 2);
      $relative_obsolute_path3 = substr($argv[2], 0, 3);

      /*INPUT DIR PATH*/
      if(strcasecmp($relative_obsolute_path1, "/")==0){
         $input_dirPath = $argv[2];
      }
      else if (strcasecmp($relative_obsolute_path2, "./")==0){
         $input_dirPath = getcwd()."/".$argv[2];
      }
      else if (strcasecmp($relative_obsolute_path3, "../")==0){
         $input_dirPath = getcwd()."/".$argv[2];
      }
      /*se non esiste la directory di input mando un errore e chiudo l'esecuzione*/
      if (!file_exists($input_dirPath)) {
         echo $input_dirPath."\n";
          die("Folder passato come input non esistente\n".
              "Inserire un folder che contiene gli articoli che si vogliono analizzata/convertire.\n\n");
      }

      if ($analisi_toDo != 5){
         $relative_obsolute_path1 = substr($argv[3], 0, 1);
         $relative_obsolute_path2 = substr($argv[3], 0, 2);
         $relative_obsolute_path3 = substr($argv[3], 0, 3);

         /*OUTPUT DIR PATH*/
         if(strcasecmp($relative_obsolute_path1, "/")==0){
            $output_dirPath = $argv[3];
         }
         else if (strcasecmp($relative_obsolute_path2, "./")==0){
            $output_dirPath = getcwd()."/".$argv[3];
         }
         else if (strcasecmp($relative_obsolute_path3, "../")==0){
            $output_dirPath = getcwd()."/".$argv[3];
         }
         else{
            $output_dirPath = getcwd()."/".$argv[3];
         }
         /*se non esiste la directory di output la creo*/
         if (!file_exists($output_dirPath)) {
             mkdir($output_dirPath,0777,true);
         }
      }
}
else
    die("Numero parametri errato\n".
        "Inserire comando come primo parametro, la directory di input come secondo ".
        "e, come terzo, la directory di output\n\n");

$verbose_mode = false;
if(strcasecmp($argv[$argc-1],"-verbose")==0){
    $verbose_mode = true;
}


switch($analisi_toDo){
    // se abbiamo un valore dato in input allora è da fare la traduzione ed il parametro sarà la cartella degli articoli da ottimizzare
    case 0:
      if ($argc == 4)
         $title_analysis = false;
      else if ($argc == 5 && strcasecmp($argv[4],"-analisi_titoli")==0)
         $title_analysis = true;
      else{
         echo "Numero parametri errato\n\n".
         "Per convertire un insieme di articoli con/senza l'analisi dei titoli:\n".
         "    ./init  0  INPUT  OUTPUT  [ -analisi_titoli ]\n";
         break;
      }

      $obj = main_converter($input_dirPath, $output_dirPath, $title_analysis);

      /*Una volta ricevuto l'oggetto da main_converter do le informazioni all'utente*/
      if ($obj !== false)
          echo $obj["nome_pubblicazione"]."\n".
          $obj["articoli_totali"]." articoli dati in input.\n".
          $obj["articoli_convertiti"]." articoli convertiti.\n".
          $obj["articoli_non_convertibili"]["senza_riferimenti"]." articoli non convertiti perche' senza riferimenti.\n".
          $obj["articoli_non_convertibili"]["mal_formati"]." articoli non convertiti perche' mal formati.\n".
          "Tempo di esecuzione: ".$obj["execution_time"]." secondi\n\n";
      else
          echo "Conversione non riuscita.\n";
      break;

    case 1:  // se 1 allora si deve fare l'analisi dell'indice di accoppiamento
        if ($argc == 4 || ($argc == 5 && $verbose_mode)){

            /*Se è stato specificato il VERBOSE MODE allora elimino, se c'è, quella precedente e creo la nuova cartella di destinazione di tutti i risultati*/
            if($verbose_mode){
               $output_verbose_data = $output_dirPath."/indiciAggregazione_verbose_data";
               if (file_exists($output_verbose_data)) {
                   rrmdir($output_verbose_data);
               }
               mkdir($output_verbose_data, 0777, true);
            }

            $obj = main_articles_analysis($analisi_toDo, $input_dirPath, $verbose_mode, $output_verbose_data);

            /*Apro il file per l'output aggregato, ovvero i valori ottenuto analizzando tutti gli articoli*/
            $file = fopen($output_dirPath."/indici_di_aggregazione_".$obj["nome_pubblicazione"].".json", "w");
            if(!$file)
                die ("Errore nell'apertura del file per i risultati\n");
            fwrite($file, json_encode($obj, JSON_PRETTY_PRINT));
            fclose($file);
            echo "I risultati sono reperibili al seguente path: ".$argv[3]."/indici_di_aggregazione_".$obj["nome_pubblicazione"].".json\n\n";
        }
        else
            echo "Errore nella sintassi\n".
                 "Eseguire \"./init_commandLine.php\" senza parametri per avere un elenco ".
                 "dei comandi offerti e della sintatti corretta.\n\n";
        break;


    case 2:// ANALISI DEI RIFERIMENTI SU FETTE DI TESTO
        if ($argc == 5 || ($argc == 6 && $verbose_mode)){
            $num_fette = $argv[4];
            /*controllo sui dati passati dall'utente*/
            if (!is_numeric($num_fette) || $num_fette<2)
                die ("Inserire un numero di fette intero che sia uguale o superiore a 2\n");

            /*Se è stato specificato il VERBOSE MODE allora elimino, se c'è, quella precedente e creo la nuova cartella di destinazione di tutti i risultati*/
            if($verbose_mode){
               $output_verbose_data = $output_dirPath."/verbose_fetteTesto(".$num_fette.")";
               if (file_exists($output_verbose_data)) {
                   rrmdir($output_verbose_data);
               }
               mkdir($output_verbose_data, 0777, true);
            }

            $obj = main_articles_analysis($analisi_toDo, $input_dirPath, $verbose_mode, $output_verbose_data, $num_fette);

            /*Apro il file per l'output aggregato, ovvero i valori ottenuto analizzando tutti gli articoli*/
            $file = fopen($output_dirPath."/fette_di_testo(".$num_fette.")_".$obj["nome_pubblicazione"].".json", "w");
            if(!$file)
                die ("Errore nell'apertura del file per i risultati\n");
            fwrite($file, json_encode($obj, JSON_PRETTY_PRINT));
            fclose($file);
            echo "I risultati sono reperibili al seguente path: ".$argv[3]."/fette_di_testo(".$num_fette.")_".$obj["nome_pubblicazione"].".json\n\n";
        }
        else
            echo "Errore sintattico\n".
                 "Eseguire \"./init_commandLine.php\" senza parametri per avere un elenco ".
                 "dei comandi offerti e della sintatti corretta.\n\n";
        break;


    case 3:// ANALISI DEI RIFERIMENTI SU SEZIONI SIGNIFICATIVE
        if ($argc == 4 || ($argc == 5 && $verbose_mode)){

            /*Se è stato specificato il VERBOSE MODE allora elimino, se c'è, quella precedente e creo la nuova cartella di destinazione di tutti i risultati*/
            if($verbose_mode){
               $output_verbose_data = $output_dirPath."/verbose_sezioniRilevanti";
               if (file_exists($output_verbose_data)) {
                   rrmdir($output_verbose_data);
               }
               mkdir($output_verbose_data, 0777, true);
            }

            $obj = main_articles_analysis($analisi_toDo, $input_dirPath, $verbose_mode, $output_verbose_data);

            /*Apro il file per l'output aggregato, ovvero i valori ottenuto analizzando tutti gli articoli*/
            $file = fopen($output_dirPath."/sezioni_rilevanti_".$obj["nome_pubblicazione"].".json", "w");
            if(!$file)
                die ("Errore nell'apertura del file per i risultati\n\n");
            fwrite($file, json_encode($obj, JSON_PRETTY_PRINT));
            fclose($file);
            echo "I risultati sono reperibili al seguente path: ".$argv[3]."/sezioni_rilevanti_".$obj["nome_pubblicazione"].".json\n\n";
        }
        else
            echo "Errore nella sintassi\n".
                 "Eseguire \"./init_commandLine.php\" senza parametri per avere un elenco ".
                 "dei comandi offerti e della sintatti corretta.\n\n";
        break;


    case 4://  ANALISI DEI RIFERIMENTI SU (INSIEMI DI SEZIONI)
        if ($argc == 5 || ($argc == 6 && $verbose_mode)){
            $num_insiemi = $argv[4];

            /*controllo sui dati passati dall'utente*/
            if (!is_numeric($num_insiemi) || $num_insiemi<2)
                die ("Inserire un numero di insiemi che sia uguale o superiore a 2\n");

            /*Se è stato specificato il VERBOSE MODE allora elimino, se c'è, quella precedente e creo la nuova cartella di destinazione di tutti i risultati*/
            if($verbose_mode){
               $output_verbose_data = $output_dirPath."/verbose_insiemi_di_sezioni(".$num_insiemi.")";
               if (file_exists($output_verbose_data)) {
                   rrmdir($output_verbose_data);
               }
               mkdir($output_verbose_data, 0777, true);
            }

            $obj = main_articles_analysis($analisi_toDo, $input_dirPath, $verbose_mode, $output_verbose_data, $num_insiemi);

            /*Apro il file per l'output aggregato, ovvero i valori ottenuto analizzando tutti gli articoli*/
            $file = fopen($output_dirPath."/insiemi_di_sezioni(".$num_insiemi.")_".$obj["nome_pubblicazione"].".json", "w");
            if(!$file)
                die ("Errore nell'apertura del file per i risultati\n");
            fwrite($file, json_encode($obj, JSON_PRETTY_PRINT));
            fclose($file);
            echo "I risultati sono reperibili al seguente path: ".$argv[3]."/insiemi_di_sezioni(".$num_insiemi.")_".$obj["nome_pubblicazione"].".json\n\n";
        }
        else
            echo "Errore nella sintassi\n".
                 "Eseguire \"./init_commandLine.php\" senza parametri per avere un elenco ".
                 "dei comandi offerti e della sintatti corretta.\n\n";
        break;

    case 5:
        if ($argc == 3)
            main_titles_analysis($input_dirPath);
        else if ($argc == 4){
            $percentuale_min = $argv[3];
            if (!is_numeric($percentuale_min) || $percentuale_min>100 || $percentuale_min<10)
                die ("Inserire una percentuale minima che vada da 10% al 100%\n");
            main_titles_analysis($input_dirPath, $percentuale_min);
        }
        else
            echo "Errore nella sintassi\n".
                 "Eseguire \"./init_commandLine.php\" senza parametri per avere un elenco ".
                 "dei comandi offerti e della sintatti corretta.\n\n";
        break;

    default:
        echo "Errore nella sintassi\n".
            "Eseguire \"./init_commandLine.php\" senza parametri per avere un elenco ".
            "dei comandi offerti e della sintatti corretta.\n\n";
        break;
}

?>
