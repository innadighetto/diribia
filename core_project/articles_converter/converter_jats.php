<?php

class converter_jats extends converter_interface{  

    private $verification_Value = 7;
    /*      VERIFICATION_VALUE
     *  se la lungh. del testo dopo un riferimento è minore di 7 PROBABILMENTE vuol dire che avrò, ad esempio:
     *  "13, 14"
     *  "13, and 14)"
     *  INVECE
     *  se la lungh. del testo dopo un riferimento è minore di 7 PROBABILMENTE vuol dire che avrò, ad esempio:
     *  "13, dopo tanti anni siamo anco..."
     *  "46 - ma anche molti altri sono..."
    */
    
    public function __construct($document) {
        parent::__construct($document);
    }
    
    
    //individuo il numero di riferimenti annidati
    protected function lookForNearRef ($ref){
        $textBetween = $ref->nextSibling;
        if (is_null($textBetween)){
            $this->ref_count++;
            return $this->ref_count;
        }
        else{
            if (preg_match ('/^\s*–/', $textBetween->nodeValue)){
                $lengh = strlen($textBetween->nodeValue);
                $ref2 = $textBetween->nextSibling;
                if ($lengh < $this->verification_Value && !is_null($ref2) && strcasecmp($ref2->nodeName,"xref")==0){
                    $ref_id = intval($ref->nodeValue);
                    $ref2_id = intval($ref2->nodeValue);
                    if($ref2_id < $ref_id)
                        $this->ref_count += ($ref_id - $ref2_id);
                    else
                        $this->ref_count += ($ref2_id - $ref_id);
                    $ref->parentNode->removeChild($ref);
                    return (-2);
                }
                else{
                    $this->ref_count++;
                    return $this->ref_count;
                }
            }
            else if (preg_match ('/^\s*−/', $textBetween->nodeValue)){
                $lengh = strlen($textBetween->nodeValue);
                $ref2 = $textBetween->nextSibling;
                if ($lengh < $this->verification_Value && !is_null($ref2) && strcasecmp($ref2->nodeName,"xref")==0){
                    $ref_id = intval($ref->nodeValue);
                    $ref2_id = intval($ref2->nodeValue);
                    if($ref2_id < $ref_id)
                        $this->ref_count += ($ref_id - $ref2_id);
                    else
                        $this->ref_count += ($ref2_id - $ref_id);
                    $ref->parentNode->removeChild($ref);
                    return (-2);
                }
                else{
                    $this->ref_count++;
                    return $this->ref_count;
                }
            }
            else if (preg_match ('/^\s*,/', $textBetween->nodeValue)){
                $lengh = strlen($textBetween->nodeValue);
                $ref2 = $textBetween->nextSibling;
                if($lengh < $this->verification_Value && !is_null($ref2) && strcasecmp($ref2->nodeName,"xref")==0){
                    $ref->parentNode->removeChild($ref);
                    $this->ref_count++;
                    return (-1);
                }
                else{
                    $this->ref_count++;
                    return $this->ref_count;
                }
            }
            else if (preg_match ('/^\s*;/', $textBetween->nodeValue)){
                $lengh = strlen($textBetween->nodeValue);
                $ref2 = $textBetween->nextSibling;
                if($lengh < $this->verification_Value && !is_null($ref2) && strcasecmp($ref2->nodeName,"xref")==0){
                    $ref->parentNode->removeChild($ref);
                    $this->ref_count++;
                    return (-1);
                }
                else{
                    $this->ref_count++;
                    return $this->ref_count;
                }
            }
            else{
                $this->ref_count++;
                return $this->ref_count;
            }
        }
    }
    
    
    protected function generalizzaRiferimenti(){
        
        $list_multi_Xref = $this->xpath->query('./body//xref[@ref-type=\'bibr\'] | ./front//xref[@ref-type=\'bibr\']',$this->article);
        $lastRef = null;
        $string_refid="";
        foreach($list_multi_Xref as $ref){
            /*  se nell'attibuto rid ho il numero di riferimenti li conto.
             *  E' possibile verificare se questo attributo contiene il num dei riferimenti verificando
             *  se abbiamo uno o più spazio.
             *  Esempio: rid="b0001 b0010"  */
            $lastRef = $ref;
            $id_ref = $ref->getAttribute("rid");
            $num_id_ref = split(" ",$id_ref);
            if (count($num_id_ref)>1){
                $ref->setAttribute("refid",$id_ref);
                $ref = $this->changeTagName($ref,"references");
                $ref->setAttribute("ref_num",(string)count($num_id_ref));
                $ref->nodeValue ="???".(string)count($num_id_ref)."]]]";
                //rimuovo l'attributo ref-type e rid poichè non servono nel formato intermedio
                $ref->removeAttribute("rid");
                $ref->removeAttribute("ref-type");
            }
            //  sennò devo fare tutta una analisi con l'ausilio della funzione lookForNearRef
            else{
                $string_refid .= " ".$ref->getAttribute("rid");
                $data = $this->lookForNearRef($ref);
                if ($data == -2) // in questo caso aggiungo un trattino nel refid poichè ho incontrato in "-" nell'articolo
                    $string_refid .= " -"; 
                else if($data != -1){   // vuol dire che posso confermare e aggiungere info al rif corrente poichè non ha più rif. aggregati
                    $ref->setAttribute("refid",$string_refid);
                    $ref = $this->changeTagName($ref,"references");
                    $ref->setAttribute("ref_num",(string)$data);
                    $ref->nodeValue ="???".(string)$data."]]]";
                    $this->ref_count = 0;
                    $string_refid="";
                    //rimuovo l'attributo ref-type e rid poichè non servono nel formato intermedio
                    $ref->removeAttribute("rid");
                    $ref->removeAttribute("ref-type");
                }
                //se invece data == -1 cicla ancora come nel caso di -2 poichè potremmo ancora avere rif. aggregati da aggiungere
            }
        }
        
        if ($list_multi_Xref->length == 0)
            return false;
        else
            return true;        
    }
    
    

    /*   Questo modulo si occupa di convertire tutti i nomi dei tag contenenti i riferimenti a "section"
     *   e i nome dei tag contenenti i titoli delle sezioni a "section-title".
     *   Nel caso in cui ci fossero dei semplici "p" o "para" dove mi aspetto di trovare le sezioni allora li
     *   aggiungo tutti ad un nuovo elemento chiamato "section" con "section-title": autogenerated_section.
     */
    protected function generalizzaSezioni(){
        
        $body = $this->xpath->query('./body',$this->article)->item(0);
        
        //  trasformo i tag "p" dentro body in sec "autogenerated"
        $newSection = $this->doc->createElement("sec");
        $title_autogen_sec = $this->doc->createElementNS ( $this->prefix_uri ,"plus:section-title","autogenerated_section");
        $newSection->appendChild($title_autogen_sec);
        $append_autogenerated_section = false;
        foreach ($body->childNodes as $child){
            if(strcasecmp($child->nodeName,"sec") != 0){
                $tmp = $child->cloneNode(true);
                $newSection->appendChild($tmp);
                $append_autogenerated_section = true;
            }
            else{   //Aggiungo il mio prefisso ai tag "section-title"
                $title_tag = $this->xpath->query('./title',$child)->item(0);
                if(!is_null($title_tag))
                    $this->changeTagName($title_tag,"section-title");
            }
        }
        if ($append_autogenerated_section)
            $body->appendChild($newSection);        
    }
    
    
    public function createFinalDocument (){
        //trovo l'elemento principale(il nostro elemento root) cioè l'elemento "article"
        $this->article = $this->xpath->query('//article')->item(0);
        if(is_null($this->article))
            return -1;
        
        //  procedo alla generalizzazione dei riferimenti, se non ne vengono trovati allora ritorna false.
        $continue = $this->generalizzaRiferimenti();
        if(!$continue)
            return -2;
        
        //  procedo alla generalizzazione delle sezioni
        $this->generalizzaSezioni();
        
        //   creo un nuovo documento dopo ci saranno solo gli elementi interessanti ai fini delle nostre analisi.
        $finalDoc = new DOMDocument('1.0', 'utf-8');
        $root = $finalDoc->createElementNS ( $this->prefix_uri ,'plus:root');
        $finalDoc->appendChild($root);
        
        $root = $finalDoc->firstChild;
        
        
        $abs = $this->xpath->query('./front//abstract',$this->article);
        foreach($abs as $a){
            $tmp = $a->cloneNode(true);
            /*Aggiungo il prefisso "plus" agli elementi "abstract"*/
            $myAbs = $this->changeTagName($tmp,"abstract");
            $doc = $finalDoc->importNode($myAbs,true);
            $root->appendChild($doc);
        }
        
        $sections = $this->xpath->query('./body/sec',$this->article);
        foreach($sections as $sec){
            $tmp = $sec->cloneNode(true);
            /*Aggiungo il prefisso "plus" agli elementi "section"*/
            $mySec = $this->changeTagName($tmp,"section");
            $doc = $finalDoc->importNode($mySec,true);
            $root->appendChild($doc);
        }
        
        return $finalDoc;
    }
    
}