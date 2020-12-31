<?php namespace Larislackers\BinanceApi;


$fn[0]="Open Time";
$fn[1]="Open";
$fn[2]="High";
$fn[3]="Low";
$fn[4]="Close / Latest";
$fn[5]="Volume";
$fn[6]="Close Time";
$fn[7]="Quote Asset Vol.";
$fn[8]="No. Of Trades";
$fn[9]="Taker Buy Vol.";
$fn[10]="Taker buy quote vol.";



require('vendor/autoload.php');


$creds=explode(":",file_get_contents('./binance.api'));

$bac = new BinanceApiContainer($creds[0], $creds[1]);



#$orders = $bac -> getOrderBook(['symbol' => 'XRPUSDT']);


// set this to a higher number to flatten out the average over a longer time
$lookback_average_mins=5;
// current iteration ( for looping over the last 2 mins of data from exchange)
$ci=0;
// timestamp of last close
$lastclose=0;
// last volums
$lastvol=0;
// array of volumes
$volume_avg_array=array();
// variable for calculated average
$vol_avg = 0;
// last cent value (rounded down to two decimal places
$curr_value=0;
while(true){
        passthru("/usr/bin/clear");
        $ci=0;
        $orders = $bac -> getKlines(['symbol' => 'XRPUSDT','interval' => '1m','limit' => '2']);
        foreach(json_Decode($orders->getBody()->getContents()) as $x){
	        if($ci==0){
		    $cv=substr($x[4],0,5);
		    if($cv!==$curr_value && $cv==0){
			$curr_value=$cv;
		    }
		}
                if($ci==0 && $lastclose !==$x[6]){
        	    $lastclose=$x[6];
        	    $volume_avg_array[]=$x[5];
                    $closures=count($volume_avg_array);
                }
                for($i=0;$i<count($x)-1;$i++){
        	        if($i==0||$i==6){
        		       $x[$i]=gmdate("d/m/y H:i:s", ceil($x[$i]/1000));
        		}
                        echo(str_pad($fn[$i], 20).": ".$x[$i]."\n");
                };
                echo("\n");

                $ci++;
        };
	
$vol_avg=0;
		    if($closures<$lookback_average_mins){
                            for($a=0;$a<$closures;$a++){
        	                $vol_avg=$vol_avg+$volume_avg_array[$a];
        	            }
        		    $vol_avg=$vol_avg/$closures;
		    } else {
                            for($a=($closures-1);$a>($closures-$lookback_average_mins);$a--){
				$vol_avg=$vol_avg+$volume_avg_array[$a];
			    }
        			$vol_avg=$vol_avg/$lookback_average_mins;
		    }
$out=false;
    if(substr($x[4],0,5)!==$curr_value){
	if(substr($x[4],0,5)<$curr_value){
	    $out.="down ".substr($x[4],0,5)."!   ";
	    
	} else if(substr($x[4],0,5)>$curr_value) {
	    $out.="up ".substr($x[4],0,5)."!   ";
	} else {
	    //do nothing
	}

	echo("cv: $curr_value nv: ".substr($x[4],0,5)."\n");
	$curr_value=substr($x[4],0,5);
    }
    if($x[5]>(3*$vol_avg)){
	$out.="Large Volume";
    }
    
    if($out){
	exec("espeak \"$out\" &");
	echo($out."\n");
    }
    Echo("Average volume ($closures): $vol_avg\n");
    echo("Current value: \$$curr_value\n");
    sleep(5);
};

?>

