<?php

function main(){

    $root_dirPath = "./dati_tmp";

    $file = fopen($root_dirPath.'/data_to_chart', "r");
    if(!$file)
        die ("Errore nell'apertura del file per i risultati\n");
    $array_data = json_decode(fread($file,filesize($root_dirPath.'/data_to_chart')),true);

    $articoli_analizzati = $array_data['articoli_analizzati'];
    if ($articoli_analizzati==0){
        die("Il file in input contiene i risultati di 0 articoli analizzati.\n\n");
    }

    $average_index = 0;
    $articoli_totali_convertiti = $array_data['articoli_totali_convertiti'];
    $publication_name = $array_data['nome_pubblicazione'];
    $publication_name = str_replace("_"," ",$publication_name);

    if (strcasecmp($array_data['analisi'],"Aggregation_Index")==0){
        $analysis_title = "Analisi Aggregation Index";
        $x_axis = "Indice di aggregazione";
        $y_axis = "Perc. Presenza";
        $data_to_chart = $array_data['data_1']['values'];
        $average_index = $array_data['data_2']['values'];
        $chart_title = "Distribuzione indici di aggregazione";
    }
    else if (strcasecmp($array_data['analisi'],"Text_Slices")==0){
        $lungh_media_fette = $array_data["lunghezza_media_fette_testo"];
        $analysis_title = "Analisi Text Slices";
        $x_axis = "Fette di testo analizzate";
        $y_axis = "Num. Riferimenti";
        $data_to_chart = $array_data['data_1']['values'];
        $chart_title = "Distribuzione riferimenti su fette di testo";
    }
    else if (strcasecmp($array_data['analisi'],"Titled_Sections")==0){
        $array_presenze_sezioni = $array_data['data_4']['values'];
        $analysis_title = "Analisi Titled Sections";
        $x_axis = "Sezioni analizzate";
        $y_axis = "Num. Riferimenti";
        $data_to_chart = $array_data['data_1']['values'];
        $chart_title = "Distribuzione medie ponderate dei riferimenti su sezioni scelte";
    }
    else if (strcasecmp($array_data['analisi'],"Numberend_Sections")==0){
        $media_sezioni_per_insieme = $array_data["media_sezioni_per_insieme"];
        $analysis_title = "Analisi Numberend Sections";
        $x_axis = "Insiemi di sezioni analizzate";
        $y_axis = "Num. Riferimenti";
        $data_to_chart = $array_data['data_1']['values'];
        $chart_title = "Distribuzione riferimenti su insiemi di sezioni";
    }

    $html = <<<STRINGA_FINE
    <!DOCTYPE html>
    <html>

        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">

            <title>Tool per analisi riferimenti bibliografici</title>

            <style>
                body{
                    margin: 20px;
                }
                #chart{
                    width:70%;
                    margin-left: 20px;
                    margin-top: 20px;
                    display:inline-block;
                }
                #presenza_sezioni{
                    width:25%;
                    margin-top:20px;
                    margin-right: 20px;
                    float:right;
                    display:inline-block;
                    border: 2px;
                    border-collapse: collapse;
                    overflow-y:hidden;
                }

                td, th {
                    border: 1px solid #dddddd;
                    text-align: left;
                    padding: 8px;
                }

                #titolo, #resultInfo{
                    text-align: center;
                    display: block;
                }

                #grado_acc{
                    margin: 20px;
                }

                #copy{
                    display: none;
                    position: fixed;
                    bottom: 5px;
                    right: 10px;
                }
            </style>
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
            <link rel="stylesheet" href="http://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
        </head>
STRINGA_FINE;


    $html .= "<body>
                <h3 id=\"titolo\">".
                    $analysis_title." - ".$publication_name.
                "</h3>
                <span id=\"resultInfo\">".
                    $articoli_analizzati." articoli analizzati su ".$articoli_totali_convertiti." articoli totali";

    if (isset($lungh_media_fette))
        $html .= " - Lungh. media fette: ".$lungh_media_fette."</span>";
    else if (isset($media_sezioni_per_insieme))
        $html .= " - Media sezioni per insieme: ".$media_sezioni_per_insieme."</span>";
    else
        $html .= "</span>";


    if ($average_index!=0){
        $html .="<h4 id=\"grado_acc\">
                Indice Medio di Aggregazione: ".$average_index.
                "</h4>";
    }

    $html .="<div id=\"chart\">
                <canvas id=\"myChart\"></canvas>
            </div>";

    if(isset($array_presenze_sezioni)){
        $html .="<div id=\"presenza_sezioni\">
                 <table>
                    <tr>
                      <th>Sezione</th>
                      <th>Num. articoli</th>
                    </tr>";
        foreach($array_presenze_sezioni as $key=>$value){
            $html .= "<tr>";
            $html .= "<td>".$key."</td>";
            $html .= "<td>".$value."</td>";
            $html .= "</tr>";
        }
        $html .= "</table></div>";
    }


    $html .= '<!-- copyright -->
            <footer class="main-footer" id="copy">
                Tool realizzato da <strong>Federico Giubaldo</strong> come progetto di tesi col professore <strong>Angelo Di Iorio</strong>.
            </footer>

            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
            <!--<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>-->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.3.0/Chart.bundle.min.js"></script>


            <script>
                $(document).ready(crea_grafico);

                myChart = null;

                function crea_grafico(){

                    /*  Rimuovo il vecchio grafico per creare il nuovo  */
                    if(myChart!==null){
                        myChart.destroy();
                    }

                    var array_x = [];
                    var array_y = [];';

    foreach($data_to_chart as $key => $value){
            $html .= "array_x.push(\"".$key."\");";
            $html .= "array_y.push(".$value.");";
    }

    $html .=        "var ctx = $(\"#myChart\");
                     myChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: array_x,
                                datasets: [
                                    {
                                        label: '".$y_axis."',".
                                        "data: array_y,
                                        backgroundColor: [
                                            'rgba(255, 99, 132, 0.4)',
                                            'rgba(54, 162, 235, 0.4)',
                                            'rgba(255, 206, 86, 0.4)',
                                            'rgba(75, 222, 142, 0.4)',
                                            'rgba(153, 102, 255, 0.4)',
                                            'rgba(200, 159, 44, 0.4)',
                                            'rgba(54,100,180,0.4)',
                                            'rgba(100, 10, 255, 0.4)',
                                            'rgba(89, 206, 250, 0.4)',
                                            'rgba(250, 150, 50, 0.4)',
                                            'rgba(255, 99, 132, 0.4)',
                                            'rgba(54, 162, 235, 0.4)',
                                            'rgba(255, 206, 86, 0.4)',
                                            'rgba(75, 222, 142, 0.4)',
                                            'rgba(153, 102, 255, 0.4)',
                                            'rgba(200, 159, 44, 0.4)',
                                            'rgba(54,100,180,0.4)',
                                            'rgba(100, 10, 255, 0.4)',
                                            'rgba(89, 206, 250, 0.4)',
                                            'rgba(250, 150, 50, 0.4)'
                                        ],
                                        borderColor: [
                                            'rgba(255,99,132,1)',
                                            'rgba(54, 162, 235, 1)',
                                            'rgba(255, 206, 86, 1)',
                                            'rgba(75, 222, 142, 1)',
                                            'rgba(153, 102, 255, 1)',
                                            'rgba(200, 159, 44, 1)',
                                            'rgba(54,100,180,1)',
                                            'rgba(100, 10, 255, 1)',
                                            'rgba(89, 206, 250, 1)',
                                            'rgba(250, 150, 50, 1)',
                                            'rgba(255,99,132,1)',
                                            'rgba(54, 162, 235, 1)',
                                            'rgba(255, 206, 86, 1)',
                                            'rgba(75, 222, 142, 1)',
                                            'rgba(153, 102, 255, 1)',
                                            'rgba(200, 159, 44, 1)',
                                            'rgba(54,100,180,1)',
                                            'rgba(100, 10, 255, 1)',
                                            'rgba(89, 206, 250, 1)',
                                            'rgba(250, 150, 50, 1)'
                                        ],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    title: {
                                        display: true,
                                        text: '".$chart_title."',
                                        fontSize:18,
                                        fontStyle:\"\"
                                    },
                                    tooltips: {
                                        enabled: true
                                    },
                                    legend: {
                                        display: false
                                    },
                                    scales: {
                                        yAxes: [{
                                            scaleLabel: {
                                                display: true,
                                                labelString: '".$y_axis."',
                                                fontSize: 16
                                            },
                                            ticks: {
                                                beginAtZero:true
                                            }
                                        }],
                                        xAxes: [{
                                            scaleLabel: {
                                                display: true,
                                                labelString: '".$x_axis."',
                                                fontSize: 16
                                            },
                                        }]
                                    }
                                }
                            });
                }
                </script>

        </body>
    </html>";

    $fp = fopen($root_dirPath."/index_chart.html", "w");
    fwrite($fp, $html);
    fclose($fp);
}
?>
