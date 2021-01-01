#!/usr/bin/php
<?php 

//// (c) jp80 (jamie[at]txt3.com)
//// See https://de.zyns.com for more information regarding this code and my other projects.

namespace Larislackers\BinanceApi;

// ANSI escape sequence
$ans = chr(27) . "[";

// Headings for the Binance API responses
$fn[0] = "Open Time";
$fn[1] = "Open";
$fn[2] = "High";
$fn[3] = "Low";
$fn[4] = "Close / Latest";
$fn[5] = "Volume";
$fn[6] = "Close Time";
$fn[7] = "Quote Asset Vol.";
$fn[8] = "No. Of Trades";
$fn[9] = "Taker Buy Vol.";
$fn[10] = "Taker buy quote vol.";

// clear the terminal
echo($ans . "2J". $ans ."0;0H");

// header text
echo ("\n" . str_pad("Last Candle", 41) . "Current Candle\n" . str_repeat("-", 78));

// populate field names
for ($j = 0;$j < 11;$j++) {
	$i = $j + 4;
	echo ($ans . $i . ";0H" . str_pad($fn[$j], 20) . ":" . $ans . $i . ";42H" . str_pad($fn[$j], 20) . ":\n");
}

// draw a line under the fields
echo ($ans . "15;0H" . str_repeat("-", 78));

// we need this
require ('vendor/autoload.php');

// parse credentials file
$creds = explode(":", file_get_contents('./binance.api'));

// fire up an instance of the php binance api
$bac = new BinanceApiContainer($creds[0], $creds[1]);

// set this to a higher number to flatten out the average over a longer time
$lookback_average_mins = 5;
// current iteration ( for looping over the last 2 mins of data from exchange)
$ci = 0;
// timestamp of last close
$lastclose = 0;
// last volums
$lastvol = 0;
// array of volumes
$volume_avg_array = array();
// variable for calculated average
$vol_avg = 0;
// last cent value (rounded down to two decimal places
$curr_value = 0;
// do this forever...
while (true) {
        // current item - 0 = prev candle, 1 = curr candle
	$ci = 0;
        // get the candle data from binance
	$orders = $bac->getKlines(['symbol' => 'XRPUSDT', 'interval' => '1m', 'limit' => '2']);
        // parse candle data
        foreach (json_Decode($orders->getBody()->getContents()) as $x) {
		if ($ci == 0) {
			$cv = substr($x[4], 0, 5);
			if ($cv !== $curr_value && $cv == 0) {
				$curr_value = $cv;
			}
		}
		if ($ci == 0 && $lastclose !== $x[6]) {
			$lastclose = $x[6];
			$volume_avg_array[] = $x[5];
			$closures = count($volume_avg_array);
		}
		for ($i = 0;$i < count($x) - 1;$i++) {
		        $add="";
		        $val = $x[$i];
			if ($i > 0 && $i < 5) {
			        // strip trailing zeroes
				$val = substr($x[$i], 0, 7);
			}
			if ($i == 5 || $i == 7 || $i == 9 || $i == 10) {
			        // format volume fields
				extract(formatVolume($x[$i]));
//			        var_dump($t);
//			        die();
			}
			if ($i == 0 || $i == 6) {
			    // make times look pretty...
			    if($ci && $i==6){
				$val = gmdate("H:i:s", time());
			    } else {
				$val = gmdate("H:i:s", ceil($x[$i] / 1000));
			    }
			}
			if (!$ci) {
			        // print on the left
				echo (chr(27) . "[" . (4 + $i) . ";22H " . $val . $add . "  ");
			}
			if ($ci) {
			        // print on the right
				echo (chr(27) . "[" . (4 + $i) . ";63H " . $val . $add . "  ");
			}
		        
		};
		echo ("\n");
	        // next item...
		$ci++;
	};
    
        // logic for calculating averages and emitting alerts
    
	$vol_avg = 0;
        // if we don't have much data yet
	if ($closures < $lookback_average_mins) {
		for ($a = 0;$a < $closures;$a++) {
			$vol_avg = $vol_avg + $volume_avg_array[$a];
		}
		$vol_avg = $vol_avg / $closures;
	} else {
	// if we have at least the maximum lookback_average_mins worth of data
		for ($a = ($closures - 1);$a > ($closures - $lookback_average_mins);$a--) {
			$vol_avg = $vol_avg + $volume_avg_array[$a];
		}
		$vol_avg = $vol_avg / $lookback_average_mins;
	}
	$out = false;
	echo ($ans . "16;0H");
        // alert if value goes up/down by N.NNX
	if (substr($x[4], 0, 5) !== $curr_value) {
		if (substr($x[4], 0, 5) < $curr_value) {
			$out.= "down " . substr($x[4], 0, 5) . "!   ";
		} else if (substr($x[4], 0, 5) > $curr_value) {
			$out.= "up " . substr($x[4], 0, 5) . "!   ";
		} else {
			//do nothing
		}
	        // curr_value = price at present
		$curr_value = substr($x[4], 0, 5);
	}
        // if the current candle's volume is 3 times the average, spawn alert
	if ($x[5] > (3 * $vol_avg)) {
		$out.= "Large Volume";
	}
        // current status
	$volDisplay=formatVolume($vol_avg);
	echo ("Average volume ($closures): " . $volDisplay['val'] . $volDisplay['add'] . "\n");
	echo ("Current value: \$$curr_value\n");
	if ($out) {
	        // speak and print the alert text
		exec("espeak \"$out\" &");
		echo ($out . "\n");
	} else {
	        // else erase previous alert from screen
		echo (str_repeat(" ", 40));
	}
        // how often to update
	sleep(1);
// end of while(true) loop
};

// utility functions

function formatVolume($text){
	$add="";
	if($text<1000000){
		$val = substr($text / 1000, 0, 5);
		$add="K";
	} else if($text>999999){
		$val = substr($text / 1000000, 0, 5);
	       	$add="M";
	} else {
		$val=substr($text, 0,5);
		$add="";
	}
	$r['val']=$val; $r['add']=$add;
	return($r);
}


?>

