<?php

class references_analysis {

    private $doc;
    private $xpath;
    public $section_for_eachSet;
    /*valore che mi indica il numero di sezioni che conterrà ciascun insieme*/
    public $charNumber_for_eachSlices;
    /*valore che mi indica il numero di caratteri che conterrà ciascuna fetta*/

    public function __construct($document) {
        $this->doc = $document;
        $this->xpath = new DOMXPath($this->doc);
        $this->xpath->registerNamespace('plus', 'http://www.unibo/plus/');
        $this->section_for_eachSet=0;
    }

    /*  funzione richiamata per contare i riferimenti in ogni elemento figlio di ROOT e creare un array
     *  con chiave il nome dell'elemento e come valore il numero di riferimenti.
     *  Gli elementi figli di ROOT sono determinati dal tipo di analisi:
     *  1) analisi sezioni significative (figli di root: abstract,introduction,methods,etc)
     *  2) analisi parti generiche (figli di root: parte_0,parte_1,parte_2,etc)
     */
    private function references_counter($currentDoc,$xpath){
        $array_Rif = array();
        $currentRoot = $currentDoc->firstChild;
        foreach($currentRoot->childNodes as $child){
            $count = 0;
            $references = $xpath->query('.//plus:references',$child);
            foreach($references as $ref){
                $count += $ref->getAttribute("ref_num");
            }
            $array_Rif[$child->nodeName] = $count;
        }
        return $array_Rif;
    }

    /* Funzione ausiliare usata da "referencesCount_text_slices" per rilevare tutte le posizioni
     * delle stringhe "???XX]]]" che identificano dei riferimenti annidati.
     */
    private function strpos_all($haystack, $needle) {
        $offset = 0;
        $allpos = array();
        while (($pos = strpos($haystack, $needle, $offset)) !== FALSE) {
            $offset = $pos + 1;
            $test_string = substr($haystack,$pos,4);
            // se sono nel caso "????XX" allora aumento l'offset di 1 e basta, al pross giro avrò "???XX"
            if(strcasecmp($test_string,"????")==0)
                continue;
            $allpos[] = $pos;
        }
        return $allpos;
    }

    /* Funzione richiamata per contare i riferimenti in ogni elemento figlio di ROOT e creare un array
     * con chiave il nome dell'elemento e come valore il numero di riferimenti.
     * La differenza con la funzione "references_counter" è che questa viene richiamata quando abbiamo
     * l'analisi sulle fette di testo e quindi non abbiamo nessuno elemento(es:references) che ci da
     * informazioni. Quindi si procede individuando le stringhe "???XX]]]" dove XX indica il num. di
     * riferimenti annidati. Queste stringhe vengono costruite in fase di traduzione.
     */
    private function referencesCount_text_slices($dom){
        $array_Rif = array();
        $root = $dom->firstChild;
        foreach($root->childNodes as $child){
            $count = 0;
            $array_pos= $this->strpos_all($child->nodeValue, "???");
            foreach ($array_pos as $value){
                /* Uso 7 come lunghezza partendo dal valore "value" poichè così
                 * sono sicuro che avrò ???X]]] OPPURE ???XX]] OPPURE ???XXX] e quindi dopo aver usato
                 * replace mi assicuro che mi rimarra solo X o XX o XXX cioè il num. di rif. annidati.
                 */
                $tmp = substr($child->nodeValue,$value,7);
                $tmp = str_replace("?","",$tmp);
                $tmp = str_replace("]","",$tmp);
                $count += intval($tmp);
            }
            $array_Rif[$child->nodeName] = $count;
            //echo $child->nodeName." ".$count."\n";
        }
    return $array_Rif;
    }


    /*  dividi_fetteTesto ritorna un array con chiave-valore
     *  chiave === elemento preso in considera
     *  valore === numero riferimenti dentro l'elemento in questione(ovvero la chiave)
     */
    public function text_slices_analysis($slices_num){
        $currentDoc = $this->doc->cloneNode(true);
        $allText = utf8_encode($currentDoc->firstChild->nodeValue);

        if (intval(strlen($allText)/10)<=$slices_num)
            return false;

        $doc_to_analyze = new DOMDocument('1.0', 'utf-8');
        $root = $doc_to_analyze->createElement("root");
        $doc_to_analyze->appendChild($root);

        $slice_length = intval(strlen($allText)/$slices_num);  // lungh. tot. / num_parti passate
        $this->charNumber_for_eachSlices = $slice_length;
        $remaining_char = strlen($allText)%$slices_num;  // caratteri restanti fuori dalla divisione che aggiungo all'ultima fetta
        $slices_array = [];


        /* Seguono la divizione in fette e i controlli per vedere se spezzo il testo su un riferimento o meno
         * (ipotizzo che i rif. sono al più 999 e quindi 3 valori (???XXX]]])
         * Fondamentale è avere i 3 punti interrogati che indicano l'inizio di riferimenti annidati
         * e i valori che indicano l'annidamento nella stessa parte, sennò perdo informazioni.
         * Quindi analizzo due casi:
         * 1) se la fetta viene spezzato su un punto interrogati
         * 2) se la fetta viene spezzato su un valore numerico
         */
        $skipped_char = 0;
        /* skipped_char mi definisce(per ogni fetta) i caratteri che devo recuperare per l'eventuale spezzettamento
         * precoce dovuto alla presenza di un punto interr. o un numero.
         * Nell'esempio identifichero il taglio con un ppipe --> |
         * ESEMPIO "citando loro e gli altri(??|?XX]]]) etc"
         * diventa una fetta cosi--> "citando loro e gli altri("
         * l'altra cosi --> "???XX]]]) etc"
         * QUINDI nell'esempio skipped-chars sara 2 poichè tolgo due caratteri dalla fetta i-esimi per
         * anticipare l'inizio dell'i-esima+1 di questo numero.
         */
        for($i=0; $i<$slices_num; $i++){
            $pos_primo_carattere = ($slice_length*$i)+$skipped_char;
            $pos_last_char = ($slice_length*($i+1));
            $slice_length_current = $slice_length-$skipped_char;

            $skipped_char = 0;
            if($i+1 == $slices_num){ //se sto definendo l'ultima fetta aggiungo i caratteri derivati dal resto della divisione iniziale.
                $slice_length_current += $remaining_char;
                $pos_last_char += $remaining_char;
            }
            else{
                if(strcasecmp($allText{$pos_last_char},"?") == 0){
                    $to_verify = substr($allText, $pos_last_char-2, 5);
                    $string_start=strpos($to_verify,"???");
                    if ($string_start !== false){
                        $skipped_char += $string_start-3;
                        $slice_length_current += $skipped_char;
                    }
                }
                else if (is_numeric($allText{$pos_last_char})){

                    $to_verify = substr($allText, $pos_last_char-5, 5);
                    $string_start=strpos($to_verify,"???");
                    if ($string_start !== false){
                        $skipped_char += $string_start-6;
                        $slice_length_current += $skipped_char;
                    }
                }
            }

            //testo che identifica una fetta
            $text = substr($allText,$pos_primo_carattere,$slice_length_current);

            //creo un nuovo elemento con dentro il testo(fetta) dell'iesima iterata
            $id_fetta = $i+1;
            $slices_array[$i] = $doc_to_analyze->createElement("fetta_".$id_fetta);
            $slices_array[$i]->appendChild($doc_to_analyze->createTextNode($text));

            //appendo l'elemento appena creato al nostro elemento principale "root"
            $root->appendChild($slices_array[$i]);
            //$doc_to_analyze->save("ciao.xml");
        }
        return $this->referencesCount_text_slices($doc_to_analyze);
    }


    /*  dividi_sezioniRilevanti ritorna un array con chiave-valore
     *  chiave === elemento preso in considera
     *  valore === numero riferimenti dentro l'elemento in questione(ovvero la chiave)
     *  La funzione usa un file di configurazione, creato in fase di traduzione che indica i titoli delle sezioni
     *  più frequentemente usati nel set di articoli.
     *  Cicla tutte le sezione dell'articoli e se un titolo è "simile" ad un valore dell'array dei titoli frequenti
     *  allora verrà metto come figlio diretto di root e ne saranno conteggiati i riferimenti.
     */
    public function relevant_sections_analysis($relevant_sections_title){
        $currentDoc = $this->doc->cloneNode(true);
        $currentRoot = $currentDoc->firstChild;
        $xpath = new DOMXPath($currentDoc);

        $isThere = [];
        $sezione_iesima = [];
        $abstractKey = -1;  // variabile che mi serve a definire se l'articolo presenza o meno "abstract"
        $thereIsOther = false;
        $parte_other = $currentDoc->createElement("Other");

        foreach($relevant_sections_title as $key=>$title){
            $newTitle = preg_replace("/[^A-Za-z0-9]/", "_",$title);
            $newTitle = preg_replace("/^[0-9.,$;_]+/","",$newTitle);
            $sezione_iesima[$key] = $currentDoc->createElement($newTitle);
        }

        $abstract = $xpath->query('/plus:root/plus:abstract');
        foreach ($relevant_sections_title as $key=>$title){
            if (stripos($title,"Abstract")!==false){
                foreach($abstract as $a){
                    $toParte_iesima = $a->cloneNode(true);
                    $sezione_iesima[$key]->appendChild($toParte_iesima);
                    $a->parentNode->removeChild($a);
                    $isThere[$key] = true;
                }
                $abstractKey = $key;
                unset($relevant_sections_title[$key]);
                break;
            }
        }

        $sections = $xpath->query('/plus:root/plus:section');
        foreach($sections as $sec){
            $toParte_iesima = $sec->cloneNode(true);
            $titleSec = $xpath->query('./plus:section-title',$sec)->item(0);
            if (!is_null($titleSec)){
                $maxPerc = 0;
                $noOtherSection = false;
                foreach($relevant_sections_title as $key=>$title){
                    similar_text($titleSec->nodeValue,$title,$perc);
                    if ($perc >= 51){
                        $noOtherSection = true;
                        if($perc>$maxPerc){
                            $maxPerc = $perc;
                            $actualKey = $key;
                        }
                    }
                }
                /* Se ho trovato una corrispondenza allora lo aggiungo al DOM dentro uno specifico tag*/
                if ($noOtherSection){
                    $sezione_iesima[$actualKey]->appendChild($toParte_iesima);
                    $sec->parentNode->removeChild($sec);
                    $isThere[$actualKey] = true;
                }
                /* SENNO aggiungo all'elemento "other"*/
                else{
                    $parte_other->appendChild($toParte_iesima);
                    $sec->parentNode->removeChild($sec);
                    $thereIsOther = true;
                }
            }
            /*se il titoli non fa match con nessuno allora lo aggiungo all'elemento "other" */
            else{
                $parte_other->appendChild($toParte_iesima);
                $sec->parentNode->removeChild($sec);
                $thereIsOther = true;
            }
        }

        if (empty($sections) && empty($abstract)){
            $emptyArray = array();
            return $emptyArray;
        }

        /*Se l'abstract è tra le sezioni da tracciare allora lo aggiungo al DOM dentro uno specifico tag*/
        if ($abstractKey != -1){
            $currentRoot->appendChild($sezione_iesima[$abstractKey]);
        }
        /* SENNO' prendo gli abstract è li elimino dal DOM*/
        else {
            $abstract = $xpath->query('/plus:root/plus:abstract');
            foreach($abstract as $a)
                $a->parentNode->removeChild($a);
        }

        // Per ogni elemento dell'array dei titolo frequenti, vedi quali sono stati trovati nell'articolo e li aggiungo a root.
        foreach($relevant_sections_title as $key=>$title){
            if($isThere[$key])
                $currentRoot->appendChild($sezione_iesima[$key]);
        }

        // Se vi sono sezioni senza titoli e quindi non analizzabili o con titoli che non hanno trovato un match allora aggiungo l'elemento "other" a "root"
        if($thereIsOther){
            $currentRoot->appendChild($parte_other);
        }

        return $this->references_counter($currentDoc,$xpath);
    }


    /*  dividi_partiGeneriche ritorna un array con chiave-valore
     *  chiave === elemento preso in considera
     *  valore === numero riferimenti dentro l'elemento in questione(ovvero la chiave)
     *  Differentemente dalla divisione in fette del testo, qui i tag rimangono e quindi non spezziamo il testo
     *  bensì le sezioni per intero.
     *  ES. se viene chiesta una divisione in 3 parti generiche allora si prenderanno tutte le sezione dell'articolo più
     *  l'abstract se c'è e si divideranno in 3 parti se la divisione non da resto 0 allora nella prima parte verranno
     *  inserite in num. di sezioni identificate dalla divisione più un num. di sezioni uguale al resto della divisione.
     */
    public function sections_sets_analysis($sets_num){
        $currentDoc = $this->doc->cloneNode(true);
        $currentRoot = $currentDoc->firstChild;
        $xpath = new DOMXPath($currentDoc);

        $thereis_abstract = false;
        $sets_array = [];
        $sections_list = [];
        $index_sections_list = 0;
        $sections_counter = 0;

        $all_sections_el = $xpath->query('/plus:root/plus:section');
        $all_abstract_el = $xpath->query('/plus:root/plus:abstract');
        if (!empty($all_abstract_el)){
            $thereis_abstract = true;
            $sections_counter = $all_sections_el->length+1;
        }
        else
            $sections_counter = $all_sections_el->length;

        if($sections_counter >= $sets_num){
            /* Se ci sono 1 o più elementi abstract li inserisco in un tag "all_abstract" per averli tutti insieme
             * e lo inserisco nell'array che conterrà tutte le sezioni(tratto l'abstract come sezione)
             */
            if($thereis_abstract){
                $abs_element = $currentDoc->createElement("all_abstract");
                foreach($all_abstract_el as $abs)
                    $abs_element->appendChild($abs);
                $currentRoot->appendChild($abs_element);
                $sections_list[0] = $abs_element;
            }
            // Aggiungo ogni sezione dell'array contenente le sezioni dell'articolo
            $z = 1;
            foreach ($all_sections_el as $sec){
                $sections_list[$z] = $sec;
                $z++;
            }
            /* Identifico il num. di sezioni($section_for_eachSet) che dovranno andare in ogni parte delle $sets_num
             * definite dall'utente. In più calcolo il rest derivato dalla divisione per ottenere $section_for_eachSet
             * che mi individua il num. di sezioni da inserire in più nella prima parte.
             */
            $this->section_for_eachSet = intval($sections_counter/$sets_num);
            $rest = $sections_counter%$sets_num;

            for($i=0; $i<$sets_num; $i++){
                $id_insieme = $i+1;
                $sets_array[$i] = $currentDoc->createElement("insieme_".$id_insieme);
                // Con il for seguente inserisco all'elemento "parte_IESIMA" un num. di sezioni uguale a $section_for_eachSet
                for($j=0;$j<$this->section_for_eachSet;$j++){
                    $section = $sections_list[$index_sections_list]->cloneNode(true);
                    $sets_array[$i]->appendChild($section);
                    $sections_list[$index_sections_list]->parentNode->removeChild($sections_list[$index_sections_list]);
                    $index_sections_list++;
                }
                // Se $i == 0 e quindi sto costruendo l'elemento "parte_0" aggiungo ad esso un num. di sez. uguali al $resto
                if($i==0){
                    for($j=0;$j<$rest;$j++){
                        $section = $sections_list[$index_sections_list]->cloneNode(true);
                        $sets_array[$i]->appendChild($section);
                        $sections_list[$index_sections_list]->parentNode->removeChild($sections_list[$index_sections_list]);
                        $index_sections_list++;
                    }
                }
                // Infini inserisco l'elemento "parte_IESIMA" come figlio di root
                $currentRoot->appendChild($sets_array[$i]);
            }
            return $this->references_counter($currentDoc,$xpath);
        }
        // Se il numero di sezioni totale è minore del num. di parte in cui dividerle allora l'articolo non verrà valutato
        else{
            return array();
        }
    }


    public function total_ref(){
        $num_all_ref = 0;
        $allRef = $this->xpath->query('//plus:references');
        foreach($allRef as $ref){
            $num_all_ref += $ref->getAttribute("ref_num");
        }
        return $num_all_ref;
    }


    // Funzione che ritorna il valore medio di aggregazione relativo ai riferimenti bib.
    public function average_aggregationRef_index(){
        $num_occurrences = array();
        $allRef = $this->xpath->query('//plus:references');
        foreach($allRef as $ref){
            $index_aggregates_ref = $ref->getAttribute("ref_num");
            $num_occurrences[$index_aggregates_ref]++;
        }

        $denominatore = 0;
        $numeratore = 0;
        foreach($num_occurrences as $index=>$repetitions){
            $denominatore += $repetitions;
            $numeratore += ($index*$repetitions);
        }
        return (number_format($numeratore/$denominatore, 2, '.', ''));
    }


    // Funzione che ritorna la distribuzione di probabilità dei gradi di aggregazione dei riferimenti bib.
    public function probability_distribution_aggregationRef(){
        $num_occurrences = array();
        $array_distribuition = array();
        $total_ref_position = 0;
        $allRef = $this->xpath->query('//plus:references');
        foreach($allRef as $ref){
            $total_ref_position++;
            $index_aggregates_ref = $ref->getAttribute("ref_num");
            $num_occurrences[$index_aggregates_ref]++;
        }
        foreach($num_occurrences as $index=>$repetitions){
            if($index >= 10 && $index <= 20)
                $array_distribuition["10-20"] = number_format((($repetitions*100)/$total_ref_position), 2, '.', '');
            else if($index > 20 && $index <= 60)
                $array_distribuition["21-60"] = number_format((($repetitions*100)/$total_ref_position), 2, '.', '');
            else if($index > 60 && $index <= 120)
                $array_distribuition["61-120"] = number_format((($repetitions*100)/$total_ref_position), 2, '.', '');
            else if($index > 120 && $index <= 240)
                $array_distribuition["121-240"] = number_format((($repetitions*100)/$total_ref_position), 2, '.', '');
            else
                $array_distribuition[$index] = number_format((($repetitions*100)/$total_ref_position), 2, '.', '');
        }
        return $array_distribuition;
    }
}

?>
