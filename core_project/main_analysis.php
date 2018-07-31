<?php
require 'articles_analysis/references_analysis.php';
require_once 'articles_analysis/sectionTitles_analysis.php';

function main_articles_analysis($analysis_toDo, $input_dirPath, $verbose_mode=false, $output_verbose_data="", $split_number=""){
    $start = microtime(true);
    
    chdir($input_dirPath);
    chdir("..");
    $root_dirPath = getcwd();
    
    //Inizializzo le variabili che saranno inviate al javascript richiedente.
    $aggregation_index = 0;
    $probability_distr_aggregation = [];
    $array_sets_sections = [];
    $array_relevant_sections = [];
    $array_text_slices = [];
    
    $num_all_ref = 0;
    $analyzed_articles = 0;
    
    $file = fopen($root_dirPath."/conversion_results.json", "r");
    $conversion_results = json_decode((fread($file,filesize($root_dirPath."/conversion_results.json"))),true);
    fclose($file);
    $converted_articles = $conversion_results["articoli_convertiti"];
    $publication_name = $conversion_results["nome_pubblicazione"];
    
    $dir = scandir ($input_dirPath);
    if ($dir === false){
        return false;
    }
    
    switch($analysis_toDo){
        case 1: // ANALISI INDICE MEDIO AGGREGAZIONI E DISTRIBUZIONE PROBABILITA GRADI AGGREGAZIONE
            $probability_distr_aggregation = array();
            foreach ($dir as $key=>$value) {
                $nome = $value;
                if (!in_array($value,array(".",".."))){
                   if (!is_dir($input_dirPath . DIRECTORY_SEPARATOR . $value)) {
                        
                        //  Creo il DOMDocument caricando l'articolo
                        $finalDoc = new DOMDocument('1.0', 'utf-8');
                        $finalDoc->load($input_dirPath . DIRECTORY_SEPARATOR . $value);
                        
                        /*istanza di un oggetto della classe "analisi" che mi permette di prendere le info che mi interessano*/
                        $analyzer = new references_analysis($finalDoc);
                        
                        /*info relativi a indice di accoppiamento dei riferimenti dell'articolo corrente
                        *e le aggiungo all'array che contiene le info di tutti gli articoli*/
                        $aggregation_index += $analyzer->average_aggregationRef_index();
                        
                        /*distribuzione probabilita degli indici di aggregazione*/
                        $probability_distr_aggregation_TMP = $analyzer->probability_distribution_aggregationRef();
                        foreach ($probability_distr_aggregation_TMP as $key=>$value){
                            $probability_distr_aggregation[$key] += $value;
                        }
                        
                        /*riferimenti totali nell'articolo*/
                        $current_all_ref = $analyzer->total_ref();
                        $num_all_ref += $current_all_ref;
                        
                        $analyzed_articles++;
                        
                        /*Codice da eseguire se è attivo il VERBOSE MODE*/
                        if($verbose_mode){
                            $file = fopen($output_verbose_data."/".$publication_name."_".$analyzed_articles.".json", "w");
                            if(!$file)
                                die ("Errore nell'apertura del file per i risultati\n");
                            $obj["distr_prob_indici_aggregazione"] = $probability_distr_aggregation_TMP;
                            $obj["indice_medio_aggregazione"] = number_format($aggregation_index, 2, '.', '');
                            //$obj["num_totale_riferimenti"] = $current_all_ref;
                            fwrite($file, json_encode($obj));                
                            fclose($file);
                        }
                   }
                }
            }
            /*divido i valori della distribuzione di probabilità per il num. di articoli e poi riordino l'arrey*/
            foreach ($probability_distr_aggregation as $key=>$value){
                $probability_distr_aggregation[$key] = number_format(($value/$analyzed_articles), 2, '.', '');
            }
            ksort($probability_distr_aggregation);
            /**/
            
            $obj = [];
            
            /*tipo analisi*/
            $obj["analisi"]="Aggregation_Index";
            /*distr. probabilita indici aggregazione*/
            $data1["result_type"]="distr_probabilita_indici_aggregazione";
            $data1["values"]=$probability_distr_aggregation;
            /*indice medio di aggregazione*/
            $data2["result_type"]="indice_medio_aggregazione";
            $data2["values"]=number_format($aggregation_index/$analyzed_articles, 2, '.', '');
            
            $obj["data_1"]=$data1;
            $obj["data_2"]=$data2;
            
            break;
        
        
        case 2: // ANALISI DEI RIFERIMENTI SU FETTE DI TESTO(non facendo attenzione minimente alla struttura dell'articolo)
            $charNumber_for_eachSlices=0;
            foreach ($dir as $key=>$value) {
                if (!in_array($value,array(".",".."))){
                   if (!is_dir($input_dirPath . DIRECTORY_SEPARATOR . $value)) {
                   
                        $finalDoc = new DOMDocument('1.0', 'utf-8');
                        $finalDoc->load($input_dirPath . DIRECTORY_SEPARATOR . $value);
            
                        /*istanza di un oggetto della classe "analisi" che mi permette di prendere le info che mi interessano*/
                        $analyzer = new references_analysis($finalDoc);
                        /*info relativi a divisione in sezioni generiche dell'articolo corrente
                         *e le aggiungo all'array che contiene le info di tutti gli articoli*/
                        $array_text_slices_TMP = $analyzer->text_slices_analysis($split_number);
                        if ($array_text_slices_TMP == false){
                            continue;
                        }
                        foreach ($array_text_slices_TMP as $key=>$value){
                            $array_text_slices[$key] += $value;
                        }
                        
                        $current_charNumber_for_eachSlices = $analyzer->charNumber_for_eachSlices;
                        
                        $charNumber_for_eachSlices += $current_charNumber_for_eachSlices;
                        
                        /*riferimenti totali nell'articolo*/
                        $current_all_ref = $analyzer->total_ref();
                        $num_all_ref += $current_all_ref;
                        
                        $analyzed_articles++;
                        
                        /*Codice da eseguire se è attivo il VERBOSE MODE*/
                        if($verbose_mode){
                            $file = fopen($output_verbose_data."/".$publication_name."_".$analyzed_articles.".json", "w");
                            if(!$file)
                                die ("Errore nell'apertura del file per i risultati\n");
                            $obj = ["Riferimenti_Fette_Testo"=>$array_text_slices_TMP,
                                    "lunghezza_fette" => $current_charNumber_for_eachSlices];
                            fwrite($file, json_encode($obj));                
                            fclose($file);
                        }
                   }
                }
            }
        
            /*Creo oggetto che contiene le informazioni agglomerate di tutti gli articoli*/
            $obj = [];
            
            /*tipo analisi*/
            $obj["analisi"]="Text_Slices";
            
            if($analyzed_articles>0){
                $obj["lunghezza_media_fette_testo"] = number_format(($charNumber_for_eachSlices/$analyzed_articles), 2, '.', '');
                
                /*totale_riferimenti*/
                $data1["result_type"]="totale_riferimenti";
                $data1["values"]=$array_text_slices;
                
                /*media_riferimenti*//*media_riferimenti*/
                $data2["result_type"]="media_riferimenti";
                foreach ($array_text_slices as $key=>$value)
                    $array_text_slices_data2[$key] = number_format(($value/$analyzed_articles), 2, '.', '');
                $data2["values"]=$array_text_slices_data2;
                
                /*percentuale_riferimenti*/
                $data3["result_type"]="percentuale_riferimenti";
                foreach ($array_text_slices as $key=>$value)
                    $array_text_slices_data3[$key] = number_format(($value*100)/$num_all_ref, 2, '.', '');
                $data3["values"]=$array_text_slices_data3;
                
                $obj["data_1"]=$data1;
                $obj["data_2"]=$data2;
                $obj["data_3"]=$data3;
            }
                
            break;
        
        
        case 3:  // ANALISI DEI RIFERIMENTI SU SEZIONI RILEVANTI(le sezioni significative sono individuate avendo in precedenza individuato i titoli frequenti nel set di articoli)
            $myfile = @fopen($root_dirPath."/settings/file_configurazione.json", "r");
            /*Con la "@" evito che mi vengo mandato al terminare un warning che so già di ricevere.*/
            if ($myfile == false)
                die("Prima di poter eseguire questa analisi bisogna aver fatto l'analisi dei titoli frequenti.\n\n");
            $config_file = json_decode(fread($myfile,filesize($root_dirPath."/settings/file_configurazione.json")),true);
            fclose($myfile);
            $relevant_sections_title = $config_file["titoli_analisi"];
            $articles_attendance_relevant_sections = $config_file["presenze_articoli"];
            foreach ($dir as $key=>$value) {
                if (!in_array($value,array(".",".."))){
                   if (!is_dir($input_dirPath . DIRECTORY_SEPARATOR . $value)) {
                        
                        //  Creo il DOMDocument caricando l'articolo
                        $finalDoc = new DOMDocument('1.0', 'utf-8');
                        $finalDoc->load($input_dirPath . DIRECTORY_SEPARATOR . $value);
                            
                        /*istanza di un oggetto della classe "analisi" che mi permette di prendere le info che mi interessano*/
                        $analyzer = new references_analysis($finalDoc);
                        
                        /* "$relevant_sections_title" mi identifica la lista di titoli più frequente nel set di
                         * articoli analizzato
                        */
                        $array_relevant_sections_TMP = $analyzer->relevant_sections_analysis($relevant_sections_title);
                        foreach ($array_relevant_sections_TMP as $key=>$value){
                            $array_relevant_sections[$key] += $value;
                        }
                        
                        /*riferimenti totali nell'articolo*/
                        $current_all_ref = $analyzer->total_ref();
                        $num_all_ref += $current_all_ref;
                        
                        $analyzed_articles++;
                        
                        /*Codice da eseguire se è attivo il VERBOSE MODE*/
                        if($verbose_mode){
                            $file = fopen($output_verbose_data."/".$publication_name."_".$analyzed_articles.".json", "w");
                            if(!$file)
                                die ("Errore nell'apertura del file per i risultati\n");
                            $obj = ["Dati_Sezioni_Rilevanti"=>$array_relevant_sections_TMP];
                            fwrite($file, json_encode($obj));                
                            fclose($file);
                        }
                   }
                }
            }
            arsort($array_relevant_sections);
            arsort($articles_attendance_relevant_sections);
            
            /*Creo oggetto che contiene le informazioni agglomerate di tutti gli articoli*/
            $obj = [];
            
            /*tipo analisi*/
            $obj["analisi"]="Titled_Sections";
            
            /*densita_riferimenti*/
            $data1["result_type"]="densita_riferimenti";
            foreach ($array_relevant_sections as $key=>$value){
                if (strcasecmp($key,"Other")==0)
                    $array_relevant_sections_data1[$key] = number_format(($value/$analyzed_articles), 2, '.', '');
                else
                    $array_relevant_sections_data1[$key] = number_format(($value/$articles_attendance_relevant_sections[$key]), 2, '.', '');
            }
            $data1["values"]=$array_relevant_sections_data1;
            
            /*totale_riferimenti*/
            $data2["result_type"]="totale_riferimenti";
            $data2["values"]=$array_relevant_sections;
            
            /*percentuale_riferimenti*/
            $data3["result_type"]="percentuale_riferimenti";
            foreach ($array_relevant_sections as $key=>$value)
                $array_relevant_sections_data3[$key] = number_format(($value*100)/$num_all_ref, 2, '.', '');
            $data3["values"]=$array_relevant_sections_data3;
            
            /*presenza_sezioni*/
            $data4["result_type"]="presenza_sezioni_rilevani_in_articoli";
            $data4["values"]=$articles_attendance_relevant_sections;
            
            $obj["data_1"]=$data1;
            $obj["data_2"]=$data2;
            $obj["data_3"]=$data3;
            $obj["data_4"]=$data4;
            
            break;
        
        
        case 4: //  ANALISI DEI RIFERIMENTI SU (INSIEMI DI SEZIONI)
            $sectionNumber_for_eachset = 0;
            foreach ($dir as $key=>$value) {
                if (!in_array($value,array(".",".."))){
                   if (!is_dir($input_dirPath . DIRECTORY_SEPARATOR . $value)) {
                   
                        $finalDoc = new DOMDocument('1.0', 'utf-8');
                        $finalDoc->load($input_dirPath . DIRECTORY_SEPARATOR . $value);
            
                        /*istanza di un oggetto della classe "analisi" che mi permette di prendere le info che mi interessano*/
                        $analyzer = new references_analysis($finalDoc);
                        
                        $array_sets_sections_TMP = $analyzer->sections_sets_analysis($split_number);
                        if(empty($array_sets_sections_TMP))
                            continue;
                        foreach ($array_sets_sections_TMP as $key=>$value){
                            $array_sets_sections[$key] += $value;
                        }
                        
                        /*riferimenti totali nell'articolo*/
                        $current_all_ref = $analyzer->total_ref();
                        $num_all_ref += $current_all_ref;
                        
                        /* Per l'articolo corrente prendo il valore che mi identifica
                         * il numero di sezioni poste in ognuno degli x insieme voluti dall'utente.
                         */
                        $current_sectionNumber_for_eachset = $analyzer->section_for_eachSet;
                        $sectionNumber_for_eachset += $current_sectionNumber_for_eachset;
                        
                        $analyzed_articles++;
                        
                        /*Codice da eseguire se è attivo il VERBOSE MODE*/
                        if($verbose_mode){
                            $file = fopen($output_verbose_data."/".$publication_name."_".$analyzed_articles.".json", "w");
                            if(!$file)
                                die ("Errore nell'apertura del file per i risultati\n");
                            $obj["Num_sezioni_per_ogni_insieme"] = $current_sectionNumber_for_eachset;
                            $obj["Dati_Insiemi_di_Sezioni"] = $array_sets_sections_TMP;
                            fwrite($file, json_encode($obj));                
                            fclose($file);
                        }
                   }
                }
            }
            
            /*Creo oggetto che contiene le informazioni agglomerate di tutti gli articoli*/
            $obj = [];
            
            /*tipo analisi*/
            $obj["analisi"]="Numberend_Sections";
            
            if ($analyzed_articles>0){
                $obj["media_sezioni_per_insieme"] = number_format(($sectionNumber_for_eachset/$analyzed_articles), 2, '.', '');
                
                /*totale_riferimenti*/
                $data1["result_type"]="totale_riferimenti";
                $data1["values"]=$array_sets_sections;
                
                /*media_riferimenti*/
                $data2["result_type"]="media_riferimenti";
                foreach ($array_sets_sections as $key=>$value)
                    $array_sets_sections_data2[$key] = number_format(($value/$analyzed_articles), 2, '.', '');
                $data2["values"]=$array_sets_sections_data2;
                
                /*percentuale_riferimenti*/
                $data3["result_type"]="percentuale_riferimenti";
                foreach ($array_sets_sections as $key=>$value)
                    $array_sets_sections_data3[$key] = number_format(($value*100)/$num_all_ref, 2, '.', '');
                $data3["values"]=$array_sets_sections_data3;
                
                $obj["data_1"]=$data1;
                $obj["data_2"]=$data2;
                $obj["data_3"]=$data3;
            }
            
            break;
    }
    
    
    $end = microtime(true);
    /*info sul tempo di eseguzione*/
    $executionTime = $end-$start;
    
    if ($analyzed_articles>0){
        $obj["riferimenti_totali"] = $num_all_ref;
        $obj["media_riferimenti_per_articolo"] = intval($num_all_ref/$analyzed_articles);
    }
    $obj["nome_pubblicazione"] = $publication_name;
    $obj["articoli_totali_convertiti"] = $converted_articles;
    $obj["articoli_analizzati"] = $analyzed_articles;
    $obj["execution_time"] = number_format($executionTime, 2, '.', '');
    
    return $obj;
}

function main_titles_analysis($input_dirPath, $default_percentage = 40){
    $start = microtime(true);
    $title_analysis = new sectionTitles_analysis($input_dirPath);
    $title_analysis->frequently_similar_titles($default_percentage);
    $end = microtime(true);
    /*info sul tempo di eseguzione*/
    $executionTime = $end-$start;
    echo "Analisi titoli eseguita in ".number_format($executionTime, 2, '.', '')." secondi.\n";
    echo "Sono stati creati due file, uno contenente i titoli più frequenti senza soglia minima di abbattimento.\n".
    "L'altro file, ovvero il file di configurazione, contiene i titoli che hanno superato".
    " una certa soglio di presenza all'interno del set di articoli analizzati.\n";
}

?>
