#!/usr/bin/php
<?php
//// (c) jp80 (jamie[at]txt3.com)
//// See https://de.zyns.com for more information regarding this code and my other projects.

// ** If this code is useful to you, I accept donations:
// ** Paypal: https://paypal.me/jd80
// ** XRP:

namespace Larislackers\BinanceApi;
shell_exec('stty cbreak');

function non_block_read($fd, &$data) {
    $read = array($fd);
    $write = array();
    $except = array();
    $result = stream_select($read, $write, $except, 0);
    if($result === false) throw new Exception('stream_select failed');
    if($result === 0) return false;
    $data = stream_get_line($fd, 1);
    return true;
}

$debugvars=array();

function debug(){
    global $debugvars;
    echo(chr(27)."[2J".chr(27)."[0H\n\n\nHELLO\n");
    var_dump(json_encode($debugvars['move'], 0, 512));
    echo("x to exit, [ret] to go back to bot");
    $comm=readline();
    if($comm=="x") exit(); else {
        print_screen_constants();
    }
}

set_exception_handler('exception_handler');

// ANSI escape sequence
$ans = chr(27) . "[";

// Voice Command
$voice = "espeak-ng -a 20 -v en-gb ";

// alert time and amount
$alert_percent=0.1; $alert_time=30;


$movement['down']=0;
$movement['up']=0;

$move['up']=array();
$move['down']=array();

print_screen_constants();
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
// timestamp of last price announcement
$priceTs=time();
// price announcement max frequency in seconds, 0 to disable
$priceTsMax=120;
// do this forever...
$alert_last_check_time=time();
$alert_last_price=0;
$alert_concurrent_movement=0;
$add_status="";

while (true) {
if(non_block_read(STDIN, $x)) {
//    echo "Input: " . $x . "\n";
    $debugvars=get_defined_vars();
    if($x=="d") debug();
}
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
			        $prev_candle_price=$x[4];
			}
			if ($ci) {
			        // print on the right
				echo (chr(27) . "[" . (4 + $i) . ";63H " . $val . $add . "  ");
				    $curr_candle_price=$x[4];
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
		        if(time()>$priceTs+$priceTsMax){
        			$out.= "down " . substr($x[4], 0, 5) . "!   ";
			        $priceTs=time();
			}
		} else if (substr($x[4], 0, 5) > $curr_value) {
    		        if(time()>$priceTs+$priceTsMax){
        			$out.= "up " . substr($x[4], 0, 5) . "!   ";
			        $priceTs=time();
			}
		} else {
			//do nothing
		}
	        // curr_value = price at present
		$curr_value = substr($x[4], 0, 5);
	}
        // check if asset rises/falls by X% over Y time
	if(time()>($alert_last_check_time+$alert_time)){
        $seconds_elapsed=time()-$alert_last_check_time;
	    $alert_last_check_time=time();
	    if(!$alert_last_price){$alert_last_price=$curr_candle_price;}
	    if($curr_candle_price>$alert_last_price){
	        $percent_change=(($curr_candle_price-$alert_last_price)/$alert_last_price)*100;
        } else
	    if($curr_candle_price<$alert_last_price){
	        $percent_change=(($alert_last_price-$curr_candle_price)/$alert_last_price)*100;
	        $percent_change=$percent_change * -1;
        } else $percent_change = 0;
//        (float)$percent_change=((float)$curr_candle_price/100)*(float)$alert_last_price;
        //$add_status.="ccp: $curr_candle_price alp: $alert_last_price\n";
	    if($percent_change>0 && $percent_change>$alert_percent){
	        $out="Up ".number_format((float)$percent_change, 2, '.', '')."  percent in the last $seconds_elapsed seconds";
            $alert_last_price=$curr_candle_price;
            $movement['up']++;
            $move['down'][]=0;
            $move['up'][]=1;
        } else if ($percent_change<0 && abs($percent_change)>$alert_percent){
	        $out="Down ".number_format((float)$percent_change, 2, '.', '')." percent in the last $seconds_elapsed seconds";
            $alert_last_price=$curr_candle_price;
            $movement['down']++;
            $move['down'][]=1;
            $move['up'][]=0;
        }
    }
	//if((last_val/100)*current_val
        // if the current candle's volume is 3 times the average, spawn alert
	if ($x[5] > (3 * $vol_avg)) {
		$out.= "Large Volume";
	}
        // current status
	$volDisplay=formatVolume($vol_avg);
	echo ("Average volume ($closures): " . $volDisplay['val'] . $volDisplay['add'] . "\n");
	echo ("Current value: \$$curr_value\n");
    $mm_check=5;
    $mCount=count($move['up']);
    if($mCount<$mm_check) $mm_check=$mCount;
    $upc=0;$dnc=0;
    for($x=$mCount-$mm_check;$x<$mCount;$x++){
        if($move['up'][$x]==1) $upc++;
        if($move['down'][$x]==1) $dnc++;
    }
    $msum=$upc-$dnc;
    $lastupsnd=0;
    $lastdnsnd=0;
    if($upc>$dnc) $bob="Up ($upc/$dnc)"; else if ($dnc>$upc) $bob="Down (".$dnc."/$upc)                 "; else $bob="No movement                      ";
    if($upc==5) {
        if (time() - $lastupsnd > 60) {
            shell_exec("aplay ./sounds/smb_1-up.wav");
            $lastupsnd = time();
        }
    }
    if($dnc==5) {
        if (time() - $lastdnsnd > 60) {
            shell_exec("aplay ./sounds/smb_pipe.wav");
            $lastdnsnd=time();
        }
    }
    echo ("Last $mm_check movements: ".$bob."                               \n");
	echo($add_status);
	if ($out) {
	        // speak and print the alert text
		shell_exec("$voice \"$out\" &");
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

function formatVolume($text): array
{
	$add="";

        if($text<1000){
	    	$val=substr($text, 0,5);
		$add="";
        } elseif($text<1000000){
		$val = substr($text / 1000, 0, 5);
		$add="K";
	} elseif($text>999999){
		$val = substr($text / 1000000, 0, 5);
	       	$add="M";
	}
    $r=array();
	$r['val']=$val; $r['add']=$add;
	return($r);
}

function exception_handler($exception) {
  echo "Uncaught exception: " , $exception->getMessage(), "\n";
}

function print_screen_constants(){
    // Headings for the Binance API responses

    global $ans;

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
}

?>

