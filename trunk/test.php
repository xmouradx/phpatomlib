<?php
require('./lib/atomlib.php');

function ls($dir, $mask){
    $handle = opendir($dir);
    $files = Array();
    while(false !== ($file = readdir($handle))) {
        if($file ==  '.' || $file == '..') continue;

        $path = $dir . '/' . $file;
        if(is_dir($path)) $files = array_merge($files, ls($path, $mask));
        if(is_file($path) && eregi($mask, $file)) array_push($files, $path);
    }
    closedir($handle);
    return $files;
}

function test_directory($dir, $results) {
    $tests = ls($dir, '.xml$');

    $successes = 0;
    $total = 0;

    foreach($tests as $test) {
        $fp = fopen($test, "r");
        $content = '';
        while(!feof($fp)) {
            $content .= fgets($fp, 4096);
        }
        fclose($fp);
        $matches = array();
        if(preg_match_all('/Expect:\\s*(.*)/', $content, &$matches)) {
            if($matches[1][0] == '!Error') {
                $total++;
                $result = str_replace($dir, $results, $test);
                $result = str_replace(".xml", ".ser", $result);
                try {
                    if(test_single($test, $result)) {
                        $successes++;
                        print "success: $test\n";
                    }
                } catch(Exception $e) {
                   print("failure: " . $e->getMessage(). "\n"); 
                }
            }
        }
    }

    print("\n $successes/$total succeeded.\n");
}

function test_single($test, $result) {

    if(!file_exists($result)) throw new Exception('results file does not exist.');

    $parser = new AtomParser();
    $parser->debug = false;
    $parser->FILE = $test;
    if(!$parser->parse()) {
        throw new Exception($parser->error);
    } else {
        $fh = fopen($result, 'r');
        $expected = unserialize(fread($fh, filesize($result)));
        fclose($fh);

        if($expected == $parser->feed) {
            #print $parser->content;
            #print(str_repeat("#",40) . "\n");
            #print_r($parser->feed);
            return true;
        } else {
            throw new Exception('failure. objects do not match.');
        }
        
        /*
        print("expected:\n");
        print_r($expected);
        print("result:\n");
        print_r($parser->feed);

        $newfile = str_replace('feedvalidator-tests','feedvalidator-results', $file);
        $newfile = str_replace('.xml','.ser', $newfile);
        print $newfile . "\n";
        if(!file_exists($newfile)) touch($newfile);
        $fh = fopen($newfile,"r+");
        fwrite($fh,serialize($parser->feed));
        fclose($fh); 
        */
    }
}

if(count($argv) == 1) {
    test_directory('feedvalidator-tests', 'feedvalidator-results');
} else {
    switch($argv[1]) {
        case '--directory':
            test_directory($argv[2], $argv[3]);
            break;
        case '--single':
            test_single($argv[2], $argv[3]);
            break;
        default:
            print "usage test-app.php [--directory TEST-DIRECTORY TEST-RESULTS] | [--single FILE].\n";
    };
}


?>
