#!/usr/bin/php
<?php
//// (c) jp80 (jamie[at]txt3.com)
//// (c) jp80 (jamie[at]txt3.com)
//// See https://de.zyns.com for more information regarding this code and my other projects.

// ** If this code is useful to you, I accept donations:
// ** Paypal: https://paypal.me/jd80
// ** XRP: Address: rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh Tag: 104071485
// ** XRP (BEP20) Address: 0xeac9832313c854cf86f8ce79466c76f920a49a31

NameSpace jmp0x0000\BinanceBot {

    require('vendor/autoload.php');

    use \Larislackers\BinanceApi as DogApi;

    shell_exec('stty cbreak');

    set_exception_handler('exception_handler');

    $bb = new BinanceBot();
    $bb->init_conf();
    $bb->print_screen_constants();
    $bb->api = new DogApi\BinanceApiContainer($bb->conf["apiCreds"][0], $bb->conf["apiCreds"][1]);
    $bb->mainLoop();

    die();



    class BinanceBot
    {

        public $conf = array();

        public $api;

        public function formatVolume($text): array
        {
            $add = "";

            if ($text < 1000) {
                $val = substr($text, 0, 5);
                $add = "";
            } elseif ($text < 1000000) {
                $val = substr($text / 1000, 0, 5);
                $add = "K";
            } elseif ($text > 999999) {
                $val = substr($text / 1000000, 0, 5);
                $add = "M";
            }
            $r = array();
            $r['val'] = $val;
            $r['add'] = $add;
            return ($r);
        }

        public function init_conf()
        {
            $this->conf["apiCreds"] = explode(":", file_get_contents('./binance.api'));
            $this->conf["ans"] = chr(27) . "[";
            $this->conf["voice"] = "espeak-ng -a 20 -v en-gb ";
            $this->conf["alert"]["percent"] = 0.1;
            $this->conf["alert"]["frequency"] = 30; // alert time and amount
            $this->conf["alert"]["lastCheckTs"] = time();
            $this->conf["alert"]["lastPrice"] = 0;
            $this->conf["alert"]["concurrency"] = 0;
            $this->conf["movement"]['down'] = 0;
            $this->conf["movement"]['up'] = 0;
            $this->conf["move"]['up'] = array();
            $this->conf["move"]['down'] = array();
            // set this to a higher number to flatten out the average over a longer time
            $this->conf["lookback_average_mins"] = 5;
// current iteration ( for looping over the last 2 mins of data from exchange)
            $this->conf["ci"] = 0;
// timestamp of last close
            $this->conf["lastclose"] = 0;
// last volums
            $this->conf["lastvol"] = 0;
// array of volumes
            $this->conf["volume_avg_array"] = array();
// variable for calculated average
            $this->conf["vol_avg"] = 0;
// last cent value (rounded down to two decimal places
            $this->conf["curr_value"] = 0;
// timestamp of last price announcement
            $this->conf["priceTs"] = time();
// price announcement max frequency in seconds, 0 to disable
            $this->conf["priceTsMax"] = 120;
// do this forever...
            $this->conf["add_status"] = "";
            $this->conf["lastupsnd"] = 0;
            $this->conf["lastdnsnd"] = 0;
            $this->conf["candlePrice"]["this"] = 0;
            $this->conf["candlePrice"]["prev"] = 0;
        }

        public function printconf()
        {
            var_dump($this->conf);
        }

        public function non_block_read($fd, &$data)
        {
            $read = array($fd);
            $write = array();
            $except = array();
            $result = stream_select($read, $write, $except, 0);
            if ($result === false) throw new Exception('stream_select failed');
            if ($result === 0) return false;
            $data = stream_get_line($fd, 1);
            return true;
        }

        public function mainLoop()
        {
            $candlePrice =& $this->conf['candlePrice'];
            while (true) {
                if ($this->non_block_read(STDIN, $this->char_input)) {
//    echo "Input: " . $x . "\n";
                    $this->debugvars = get_defined_vars();
                    if ($this->char_input == "d") $this->debug();
                }
                // current item - 0 = prev candle, 1 = curr candle
                $ci = 0;
                // get the candle data from binance
                $orders = $this->api->getKlines(['symbol' => 'XRPUSDT', 'interval' => '1m', 'limit' => '2']);
                // parse candle data
                foreach (json_Decode($orders->getBody()->getContents()) as $x) {
                    if ($ci == 0) {
                        $this->conf['cv'] = substr($x[4], 0, 5);
                        if ($this->conf['cv'] !== $this->conf['curr_value'] && $this->conf['cv'] == 0) {
                            $this->conf['curr_value'] = $this->conf['cv'];
                        }
                    }
                    if ($ci == 0 && $this->conf['lastclose'] !== $x[6]) {
                        $this->conf['lastclose'] = $x[6];
                        $this->conf['volume_avg_array'][] = $x[5];
                        $this->conf['closures'] = count($this->conf['volume_avg_array']);
                    }
                    for ($i = 0; $i < count($x) - 1; $i++) {
                        $add = "";
                        $val = $x[$i];
                        if ($i > 0 && $i < 5) {
                            // strip trailing zeroes
                            $val = substr($x[$i], 0, 7);
                        }
                        if ($i == 5 || $i == 7 || $i == 9 || $i == 10) {
                            // format volume fields
                            extract($this->formatVolume($x[$i]));
//			        var_dump($t);
//			        die();
                        }
                        if ($i == 0 || $i == 6) {
                            // make times look pretty...
                            if ($ci && $i == 6) {
                                $val = gmdate("H:i:s", time());
                            } else {
                                $val = gmdate("H:i:s", ceil($x[$i] / 1000));
                            }
                        }
                        if (!$ci) {
                            // print on the left
                            echo(chr(27) . "[" . (4 + $i) . ";22H " . $val . $add . "  ");
                            $candlePrice['prev'] = $x[4];
                        }
                        if ($ci) {
                            // print on the right
                            echo(chr(27) . "[" . (4 + $i) . ";63H " . $val . $add . "  ");
                            $candlePrice['this'] = $x[4];
                        }

                    };
                    echo("\n");
                    // next item...
                    $ci++;
                };
                $this->doAlerts();
                sleep(1);
            }
        }

        public function print_screen_constants()
        {
            // Headings for the Binance API responses

            $ans=$this->conf['ans'];

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

            echo($ans . "2J" . $ans . "0;0H");        // clear the terminal
            echo("\n" . str_pad("Last Candle", 41) . "Current Candle\n" . str_repeat("-", 78));        // header text
            for ($j = 0; $j < 11; $j++) {         // populate field names
                $i = $j + 4;
                echo($ans . $i . ";0H" . str_pad($fn[$j], 20) . ":" . $ans . $i . ";42H" . str_pad($fn[$j], 20) . ":\n");
            }
            echo($ans . "15;0H" . str_repeat("-", 78));         // draw a line under the fields
        }


        public function debug()
        {
            global $debugvars;
            echo(chr(27) . "[2J" . chr(27) . "[0H");
            $exitDebug = false;
            while (!$exitDebug) {
                echo("\nx to exit, b to go back to bot, h for help\n");
//                exec("bash read -i hello");
                readline_add_history("my nan");
                $comm = readline();
                if ($comm == "x") exit();
                if ($comm == "b") $exitDebug = true;
                if ($comm == "h") {
                    echo("printvar <varname> - dump contents\n");
                    echo("printvar           - dump all vars\n");

                }
                if (substr($comm, 0, 8) == "printvar") {
                    foreach (array_keys($this->conf) as $key) {
                        echo($key . " : " . json_encode($this->conf[$key]) . "\n");
                    }
                }
            }
            $this->print_screen_constants();
        }

        public function doAlerts()
        {       // logic for calculating averages and emitting alerts
            $conf =& $this->conf;
            $closures =& $conf['closures'];
            $lookback_average_mins =& $conf['lookback_average_mins'];
            $volume_avg_array =& $conf['volume_avg_array'];
            $vol_avg =& $conf['vol_avg'];
            $priceTs =& $conf['priceTs'];
            $priceTsMax =& $conf['priceTsMax'];
            $curr_value =& $conf['curr_value'];
            $ans =& $conf['ans'];
            $vol_avg = &$conf['vol_avg'];
            $lastVolume = &$this->conf['lastvol'];
            $alert = &$conf['alert'];
            $candlePrice =& $this->conf["candlePrice"];
            $move =& $this->conf["move"];
            $add_status = "";
            // if we don't have much data yet
            if ($conf['closures'] < $conf['lookback_average_mins']) {
                for ($a = 0; $a < $conf['closures']; $a++) {
                    $vol_avg = $vol_avg + $conf['volume_avg_array'][$a];
                }
                $vol_avg = $vol_avg / $conf['closures'];
            } else {
                // if we have at least the maximum lookback_average_mins worth of data
                for ($a = ($closures - 1); $a > ($closures - $lookback_average_mins); $a--) {
                    $vol_avg = $vol_avg + $volume_avg_array[$a];
                }
                $vol_avg = $vol_avg / $lookback_average_mins;
            }
            $out = false;
            echo($ans . "16;0H");
            // alert if value goes up/down by N.NNX
            if (substr($candlePrice['this'], 0, 5) !== $curr_value) {
                if (substr($candlePrice['this'], 0, 5) < $curr_value) {
                    if (time() > $priceTs + $priceTsMax) {
                        $out .= "down " . substr($candlePrice['this'], 0, 5) . "!   ";
                        $priceTs = time();
                    }
                } else if (substr($candlePrice['this'], 0, 5) > $curr_value) {
                    if (time() > $priceTs + $priceTsMax) {
                        $out .= "up " . substr($candlePrice['this'], 0, 5) . "!   ";
                        $priceTs = time();
                    }
                } else {
                    //do nothing
                }
                // curr_value = price at present
                $curr_value = substr($candlePrice['this'], 0, 5);
            }
            // check if asset rises/falls by X% over Y time
            if (time() > ($alert["lastCheckTs"] + $alert["frequency"])) {
                $seconds_elapsed = time() - $alert["lastCheckTs"];

                if (!$alert["lastPrice"]) {
                    $alert["lastPrice"] = $candlePrice["this"];
                }
                if ($candlePrice["this"] > $alert["lastPrice"]) {
                    $percent_change = (($candlePrice["this"] - $alert["lastPrice"]) / $alert["lastPrice"]) * 100;
                } else
                    if ($candlePrice["this"] < $alert["lastPrice"]) {
                        $percent_change = (($alert["lastPrice"] - $candlePrice["this"]) / $alert["lastPrice"]) * 100;
                        $percent_change = $percent_change * -1;
                    } else $percent_change = 0;
                if ($percent_change > 0 && $percent_change > $alert["percent"]) {
                    $out = "Up " . number_format((float)$percent_change, 2, '.', '') . "  percent in the last $seconds_elapsed seconds";
                    $alert["lastPrice"] = $candlePrice["this"];
                    //$movement['up']++;
                    $move['down'][] = "0";
                    $move['up'][] = "1";
                    $alert['lastCheckTs'] = time();
                } else if ($percent_change < 0 && abs($percent_change) > $alert["percent"]) {
                    $out = "Down " . number_format((float)$percent_change, 2, '.', '') . " percent in the last $seconds_elapsed seconds";
                    $alert["lastPrice"] = $candlePrice["this"];
                    //$movement['down']++;
                    $move['down'][] = "1";
                    $move['up'][] = "0";
                    $alert['lastCheckTs'] = time();
                }
            }
            //if((last_val/100)*current_val
            // if the current candle's volume is 3 times the average, spawn alert
            if ($lastVolume > (3 * $vol_avg)) {
                $out .= "Large Volume";
            }
            // current status
            $volDisplay = $this->formatVolume($vol_avg);
            echo("Average volume ($closures): " . $volDisplay['val'] . $volDisplay['add'] . "\n");
            echo("Current value: \$$curr_value\n");
            $mm_check = 5;
            $mCount = count($move['up']);
            if ($mCount < $mm_check) $mm_check = $mCount;
            $upc = 0;
            $dnc = 0;
            for ($x = $mCount - $mm_check; $x < $mCount; $x++) {
                if ($move['up'][$x] == 1) $upc++;
                if ($move['down'][$x] == 1) $dnc++;
            }
            $msum = $upc - $dnc;
            if ($upc > $dnc) $bob = "Up ($upc/$dnc)"; else if ($dnc > $upc) $bob = "Down (" . $dnc . "/$upc)                 "; else $bob = "No movement                      ";
            if ($upc == 5) {
                if ((time() - $this->conf["lastupsnd"]) > 60) {
                    shell_exec("aplay ./sounds/smb_1-up.wav");
                    $this->conf["lastupsnd"] = time();
                }
            }
            if ($dnc == 5) {
                if ((time() - $this->conf["lastdnsnd"]) > 60) {
                    shell_exec("aplay ./sounds/smb_pipe.wav");
                    $this->conf["lastdnsnd"] = time();
                }
            }
            echo("Last $mm_check movements (>" . $alert["percent"] . "%): " . $bob . "                               \n");
            echo($add_status);
            if ($out) {
                // speak and print the alert text
                shell_exec($this->conf["voice"] . " \"$out\" &");
                echo($out . "\n");
            } else {
                // else erase previous alert from screen
                echo(str_repeat(" ", 40));
            }
        }

    }


// utility functions


    function exception_handler($exception)
    {
        echo "Uncaught exception: ", $exception->getMessage(), "\n";
    }

}
?>