<?php

class sectionTitles_analysis{
    
    private $input_dirPath;
    private $root_dirPath;
    private $value_for_stepDefinition = 10000;
    private $value_for_stepDefinition2 = 200;//va bene un valore tra 100 e 200
    /* Valori che utilizzo per identificare il numero di volte in cui dovrò abbattere la dimensione
     * del mio array, più sono gli articoli da analizzare più alto sarà il numero di passi per
     * l'abbattimento dell'array. (idea di base: 10 step di abbattiamento ogni 10000 articoli)
     */
    
    public function __construct($converted_articles) {
        $this->input_dirPath = $converted_articles;
        chdir($converted_articles);
        chdir("..");
        $this->root_dirPath = getcwd();
        /*root_dirPath mi indica dove andare a mettere i risultati*/
    }
    
    
    /* Questa funziona crea due file:
     * 1) contente tutti i titoli di sezioni che sono presenti in più del 10 percento degli articoli totali
     * 2) contente tutti i titoli di sezioni che sono presenti in più della percentuale definita dall'utente,
     *      se non è specificata allora sarà il 40 percento. Questo file sarà usato da altre funzione(es.dividi_sezioniRilevanti)
     *      come file di configurazione.
     */
    function frequently_similar_titles($analysis_percentage = 40){
        
        $dir = scandir ($this->input_dirPath);
        if ($dir === false){
            return false;
        }
        
        $array_keyTitles = [];
        $min_percentage = 10;
        $articoli_analizzati = 0;
        $num_article = count($dir)-2; // -2 poichè non conto il "file nascosto" "." e ".."
        $step_remove = [];
        $num_step_removal = intval($num_article*$this->value_for_stepDefinition2)/$this->value_for_stepDefinition;
        /*Num_step_removal definisce il numero di volte che l'array usato in seguita sarà sfoltito*/
        $soglia;
        /* Soglia è il valoro che ogni elemento dell'array deve superare ad ogni step
         * per non essere eliminato da quest'ultimo
         */
        
        if($num_step_removal>1){
            $articleInterval_for_step = intval($num_article/$num_step_removal);
            $soglia = $articleInterval_for_step/4;
            for ($i=1;$i<$num_step_removal;$i++){
                $step_remove[] = $articleInterval_for_step*$i;
            }
        }
        
        foreach ($dir as $key=>$value) {
            if (!in_array($value,array(".",".."))){
                if (!is_dir($dirPath . DIRECTORY_SEPARATOR . $value)) {
                    
                    //  Creo il DOMDocument caricando l'articolo
                    $article = new DOMDocument('1.0', 'utf-8');
                    $article->load($this->input_dirPath . DIRECTORY_SEPARATOR . $value);
                    $xpath = new DOMXPath($article);                        
                    
                    $articoli_analizzati++;
                    
                    $tmp_boolean = false;
                    $reduceArray_ok = false;
                    /* TMP_BOOLEAN e REDUCEARRAY_ok mi permettono di verificare sempre se la riduzione
                     * e' stata gia' fatto o meno per evitare di farla più volte durante l'analisi dello stesso articolo.
                     */
                    
                    $tmp=[];
                    /* TMP = array che mi permette di verificare se un articola ha gia aggiunto
                     * la corrispondenza di un suo titolo ad un titolo dell'$array_keyTitles, se così fosse
                     * non può più aggiungere corrispondeze a quell'elemento dell'$array_keyTitles
                     */
                    
                    $all_titles = $xpath->query('/plus:root/plus:section/plus:section-title');
                    $abstract = $xpath->query('/plus:root/plus:abstract');
                    if(!empty($abstract)){
                        $array_keyTitles["Abstract"]++;
                        $tmp["Abstract"] = 1;
                    }
                    
                    foreach($all_titles as $title){ //ciclo per ogni titolo di sezione
                        $title_matching = false; 
                        $titleSec = $title->nodeValue;
                        $maxPerc = 0;
                        
                            foreach($array_keyTitles as $key=>$value){ //es: key=abstract value=3
                                
                                /*Controllo per riduzione dimensione array*/
                                if($num_step_removal>1 && in_array($articoli_analizzati,$step_remove) && !$reduceArray_ok){
                                    $tmp_boolean = true;
                                    if ($value < $soglia){
                                        unset($array_keyTitles[$key]);
                                        continue;                     
                                    }    
                                }
                                /**/
                                
                                /* Solo se l'elemento dell'$array_keyTitles analizzato con il titolo X avrà una
                                 * percentuale di similitudine >= 51 ne terrò conto memorizzando la key e
                                 * la percentuale di similitudine. Farò questo processo per ogni elemento
                                 * dell'$array_keyTitles così da avere, dopo aver scansionato tutto l'$array_keyTitles, in maxPerc
                                 * la percentuale max di similitudine trovata con il titolo X e in
                                 * actualKey avrò la chiave che identifica l'elemento dell'$array_keyTitles che ha conseguito la
                                 * percentuale max cosi da poter aggiungere la corrispondenza del titolo X a quest'ultimo.
                                 */
                                similar_text($titleSec,$key,$perc);
                                if ($perc >= 51){
                                    if ($perc > $maxPerc){
                                        $maxPerc = $perc;
                                        $actualKey = $key;
                                    }
                                    $title_matching = true;
                                }
                            }
                            /* Se ho trovato almeno una corrispondenza allora aggiungo un +1 all'el. dell'$array_keyTitles
                             * che ha la percentuale max di similitudine con il titolo in analisi.
                             * Questo se e solo se l'elemento dell'$array_keyTitles non sia già stato incremetato da un'altro
                             * titol appartenente allo stesso articolo(verifica fatta con l'array "tmp")
                             */
                            if ($title_matching){
                                if(!isset($tmp[$actualKey])){
                                    $tmp[$actualKey] = 1;
                                    $array_keyTitles[$actualKey]++;
                                }
                            }
                            /* Se invece nessun elemento dell'array è simili al titolo analizzato con più del 51 percento
                             * allora lo aggiungo all'array dei titoli chiave.
                             */
                            else{
                                $tmp[$titleSec] = 1;
                                $array_keyTitles[$titleSec]++;
                            }
                            
                            if($tmp_boolean)
                                $reduceArray_ok = $tmp_boolean;
                    }
                    
                }
            }
        }
        if (!file_exists($this->root_dirPath."/settings")) {
            mkdir($this->root_dirPath."/settings",0777,true);
        }
        
        /*  Ciclo sull'array dei titoli chiave e prendo:
         *  1) quelli con un percentuale di presenza all'interno di tutti gli articoli superiore al 10 percento
         *  e li inserisco nell'array dei titoli più utilizzati.
         *  2) quelli con una percentuale di presenza all'interno di tutti gli articoli superiore a quella definita
         *  dall'utente (default 40%) e li inserisco in un array che rappresenta il file_configurazione
         *  da passare alle analisi successive.
         */
        $title_analysis_perc = [];
        $title_min_perc = [];
        $frequently_title_analysis_perc = [];
        foreach($array_keyTitles as $key=>$value){
            $perc_presenza_key = ($value*100)/$articoli_analizzati;
            if ($perc_presenza_key >= $min_percentage){
                $new_key = preg_replace("/\s+/","_",$key);
                $title_min_perc [$new_key] = $value;
                
                if ($perc_presenza_key >= $analysis_percentage){
                    $title_analysis_perc [] = $new_key;
                    $frequently_title_analysis_perc [$new_key] = $value;
                }
            }
        }
        
        $obj_file_config = ["titoli_analisi" => $title_analysis_perc,
                            "presenze_articoli" => $frequently_title_analysis_perc];
        
        $myfile = fopen($this->root_dirPath."/settings/file_configurazione.json", "w");
        fwrite($myfile,json_encode($obj_file_config, JSON_PRETTY_PRINT));
        fclose($myfile);
        $myfile2 = fopen($this->root_dirPath."/settings/frequently_titles.json", "w");
        arsort($title_min_perc);
        fwrite($myfile2,json_encode($title_min_perc, JSON_PRETTY_PRINT));
        fclose($myfile2);
        
    }
}
    
?>