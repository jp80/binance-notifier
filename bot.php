<?php namespace Larislackers\BinanceApi;
$ans = chr(27) . "[";
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
passthru("clear");
echo ("\n" . str_pad("Last Candle", 41) . "Current Candle\n" . str_repeat("-", 78));
for ($j = 0;$j < 11;$j++) {
	$i = $j + 4;
	echo ($ans . $i . ";0H" . str_pad($fn[$j], 20) . ":" . $ans . $i . ";42H" . str_pad($fn[$j], 20) . ":\n");
}
echo ($ans . "15;0H" . str_repeat("-", 78));
require ('vendor/autoload.php');
$creds = explode(":", file_get_contents('./binance.api'));
$bac = new BinanceApiContainer($creds[0], $creds[1]);
#$orders = $bac -> getOrderBook(['symbol' => 'XRPUSDT']);
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
while (true) {
	//        echo(chr(27)."[2J".chr(27)."[0H");
	$ci = 0;
	$orders = $bac->getKlines(['symbol' => 'XRPUSDT', 'interval' => '1m', 'limit' => '2']);
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
			if ($i > 0 && $i < 5) {
				$x[$i] = substr($x[$i], 0, 7);
			}
			if ($i == 5 || $i == 7 || $i == 9 || $i == 10) {
				$x[$i] = substr($x[$i] / 1000, 0, 5) . "K";
			}
			if ($i == 0 || $i == 6) {
				$x[$i] = gmdate("H:i:s", ceil($x[$i] / 1000));
			}
			if (!$ci) {
				echo (chr(27) . "[" . (4 + $i) . ";22H " . $x[$i] . "  ");
			}
			if ($ci) {
				echo (chr(27) . "[" . (4 + $i) . ";63H " . $x[$i] . "  ");
			}
		};
		echo ("\n");
		$ci++;
	};
	$vol_avg = 0;
	if ($closures < $lookback_average_mins) {
		for ($a = 0;$a < $closures;$a++) {
			$vol_avg = $vol_avg + $volume_avg_array[$a];
		}
		$vol_avg = $vol_avg / $closures;
	} else {
		for ($a = ($closures - 1);$a > ($closures - $lookback_average_mins);$a--) {
			$vol_avg = $vol_avg + $volume_avg_array[$a];
		}
		$vol_avg = $vol_avg / $lookback_average_mins;
	}
	$out = false;
	echo ($ans . "16;0H");
	if (substr($x[4], 0, 5) !== $curr_value) {
		if (substr($x[4], 0, 5) < $curr_value) {
			$out.= "down " . substr($x[4], 0, 5) . "!   ";
		} else if (substr($x[4], 0, 5) > $curr_value) {
			$out.= "up " . substr($x[4], 0, 5) . "!   ";
		} else {
			//do nothing
			
		}
		//	echo("cv: $curr_value nv: ".substr($x[4],0,5)."\n");
		$curr_value = substr($x[4], 0, 5);
	}
	if ($x[5] > (3 * $vol_avg)) {
		$out.= "Large Volume";
	}
	Echo ("Average volume ($closures): $vol_avg\n");
	echo ("Current value: \$$curr_value\n");
	if ($out) {
		exec("espeak \"$out\" &");
		echo ($out . "\n");
	} else {
		echo (str_repeat(" ", 40));
	}
	sleep(5);
};
?>

