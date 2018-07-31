<?php
require 'articles_converter/converter_interface.php';
require 'articles_converter/converter_elsevier.php';
require 'articles_converter/converter_jats.php';
require_once 'articles_analysis/sectionTitles_analysis.php';   
    
function deleteDirectory($dir) {
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
    
function main_converter($input_dirPath, $output_dirPath, $do_titles_analysis=false){
    $start = microtime(true);
    
    $output_converted_articles = $output_dirPath."/articoli_convertiti";
    
    $check_done = false;
    $jats_article = false;
    $elsevier_article = false;
    
    $dir = scandir ($input_dirPath);
    if ($dir === false)
        return false;
    
    $other_error_articles=0;
    $articles_with_bad_struct=0;
    $articles_without_references=0;
    $analyzed_articles = 0;
    $publication_Name = "";
    
    if (file_exists($output_converted_articles)) {
        deleteDirectory($output_converted_articles);
    }
    mkdir($output_converted_articles,0777,true);    
    
    foreach ($dir as $key=>$value) {
        $articleName = $value;
        if (!in_array($value,array(".",".."))){
           if (!is_dir($input_dirPath . DIRECTORY_SEPARATOR . $value)) {
           
                //  Controllo che l'estensione si "xml" o "nxml"
                $path_parts = pathinfo($input_dirPath . DIRECTORY_SEPARATOR . $value);
                if (strcasecmp($path_parts['extension'],"xml") != 0 && strcasecmp($path_parts['extension'],"nxml") != 0){
                    $other_error_articles++;
                    continue;
                }
                
                //  Creo il DOMDocument caricando l'articolo
                $originalDoc = new DOMDocument('1.0', 'utf-8');
                $originalDoc->load($input_dirPath . DIRECTORY_SEPARATOR . $value);
                
                /*Controllo quale sia il DTD usato: elsevir o jats(passaggio da fare una sola volta)  prendo il nome di pubblicazione
                 * ipotizzando che tutti gli articoli abbiamo lo stesso provenendo dalla stessa sorgente
                 */
                if(!$check_done){
                    $xpath = new DOMXPath($originalDoc);
                    $el_for_checkFormat = $xpath->query("/*")->item(0);
                    $attribute = $el_for_checkFormat->getAttribute('xmlns');
                    /* Prendo il primo elemento(root) e controllo il suo attributo XMLNS.
                     * Se questo attributo contiene "elsevier" allora DTD elsevier e prendo il nome di pubblicazione
                     * Se questo attributo NON contiene "elsevier" allora DTD jats e prendo il nome di pubblicazione*/
                
                    if (strpos($attribute,"elsevier")!==false){
                        $xpath->registerNamespace('prism', 'http://prismstandard.org/namespaces/basic/2.0/');
                        $publication_Name_el = $xpath->query('//prism:publicationName')->item(0);
                        if (!is_null($publication_Name_el)){
                            $publication_Name = $publication_Name_el->nodeValue;
                            $elsevier_article = true;
                            $check_done = true;
                        }
                    }
                    else{
                        $publication_Name_el = $xpath->query('//publisher-name')->item(0);
                        if (!is_null($publication_Name_el)){
                            $publication_Name = $publication_Name_el->nodeValue;
                            $check_done = true;
                            $jats_article = true;
                        }
                    }
                    
                    
                    if ($check_done){
                        $publication_Name = utf8_encode(preg_replace("/\s+/","_",$publication_Name));
                        /*Salvo i nomi delle pubblicazione in un file dentro settings*/
                        /*$file = fopen($output_dir."/nome_pubblicazione", "w");
                        if(!$file){}
                        else{
                            fwrite($file, $publication_Name);                
                            fclose($file);
                        }*/
                    }
                }
                /**/
                
                if ($elsevier_article){
                    $converter = new converter_elsevier($originalDoc);
                }
                else if($jats_article){
                    $converter = new converter_jats($originalDoc);
                }
                else
                    die("errore - Nessun DTD rilevato.");
                    
                $finalDoc = $converter->createFinalDocument();
                    
                if ($finalDoc == -1)
                    $articles_with_bad_struct++;
                else if($finalDoc == -2)
                    $articles_without_references++;
                else{
                    $analyzed_articles++;
                    $finalDoc->save($output_converted_articles."/".$articleName);   
                }
                
    
           }
        }
    }
    
    if ($do_titles_analysis){
        $title_analysis = new sectionTitles_analysis($output_converted_articles);
        $title_analysis->frequently_similar_titles();
    }
    
    $end = microtime(true);
    /*info sul tempo di eseguzione*/
    $executionTime = $end-$start;
   
    $obj = ["nome_pubblicazione"=>$publication_Name,
            "articoli_totali"=>$analyzed_articles+$other_error_articles+$articles_without_references+$articles_with_bad_struct,
            "articoli_non_convertibili" => ["senza_riferimenti" => $articles_without_references,
                                            "mal_formati" => $articles_with_bad_struct+$other_error_articles
                                        ],
            "articoli_convertiti" => $analyzed_articles,
            "execution_time"=>number_format($executionTime, 2, '.', '')
            ];
    
    $myfile = fopen($output_dirPath."/conversion_results.json", "w");
    fwrite($myfile,json_encode($obj, JSON_PRETTY_PRINT));
    fclose($myfile);
    
    return $obj;

}
?>