<?php

class converter_interface {  

    protected $doc;
    protected $xpath;
    protected $ref_count;
    protected $prefix_uri;
    protected $article;
    
    protected function __construct($document) {
        $this->doc = $document;
        $this->xpath = new DOMXPath($this->doc);
        $this->ref_count = 0;
        $this->prefix_uri = "http://www.unibo/plus/";
    }
    
    
    //  http://stackoverflow.com/questions/775904/how-can-i-change-the-name-of-an-element-in-dom
    // Funzione per rinominare gli elemento
    protected function changeTagName($node, $name) {
        // importante creare questo array perchè se lavoro direttamente sui $node->childNode non vaa ovvero la lista viene modificata        
        $childnodes = array();
        foreach ($node->childNodes as $child){
            $childnodes[] = $child;
        }
        
        $newnode = $this->doc->createElementNS ( $this->prefix_uri ,'plus:'.$name );
            
        foreach ($childnodes as $child){
            $child2 = $this->doc->importNode($child, true);
            $newnode->appendChild($child2);
        }
        
        foreach ($node->attributes as $attr) {
            $attrName = $attr->nodeName;
            $attrValue = $attr->nodeValue;
            $newnode->setAttribute($attrName, $attrValue);
        }
        
        if (!is_null($node->parentNode))
            $node->parentNode->replaceChild($newnode, $node);
            
        return $newnode;
    }
    
    protected function generalizzaRiferimenti($articleElement){}
    
    protected function generalizzaSezioni($article){}
    
    public function createFinalDocument (){}
    
}

?>