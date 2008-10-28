<?php

if (__FILE__ === realpath($_SERVER['SCRIPT_FILENAME'])) {
    // called directly
    if (isset($_GET['getOutputCompression'])) {
        echo (int)ini_get('zlib.output_compression');
        exit();
    }
    if (isset($_GET['hello'])) {
        // try to disable (may not work)
        ini_set('zlib.output_compression', '0');
        echo 'World!';
        exit();
    }
}

require_once '_inc.php';

function test_environment()
{
    global $thisDir;
    
    $thisUrl = 'http://' 
        . $_SERVER['HTTP_HOST'] // avoid redirects when SERVER_NAME doesn't match
        . ('80' === $_SERVER['SERVER_PORT'] ? '' : ":{$_SERVER['SERVER_PORT']}")
        . dirname($_SERVER['REQUEST_URI']) 
        . '/test_environment.php';
    
    $oc = @file_get_contents($thisUrl . '?getOutputCompression=1');
    
    if (false === $oc || ! preg_match('/^[01]$/', $oc)) {
        echo "!WARN: environment : Local HTTP request failed. Testing cannot continue.\n";
        return;
    }
    if ('1' === $oc) {
        echo "!WARN: environment : zlib.output_compression is enabled in php.ini or .htaccess.\n";
    }
    
    $fp = fopen($thisUrl . '?hello=1', 'r', false, stream_context_create(array(
        'http' => array(
            'method' => "GET",
            'header' => "Accept-Encoding: deflate, gzip\r\n"
        )
    )));
    
    $meta = stream_get_meta_data($fp);
    
    $passed = true;
    foreach ($meta['wrapper_data'] as $i => $header) {
        if ((preg_match('@^Content-Length: (\\d+)$@i', $header, $m) && $m[1] !== '6')
            || preg_match('@^Content-Encoding:@i', $header, $m)
        ) {
            $passed = false;
            break;
        }
    }
    if ($passed && stream_get_contents($fp) !== 'World!') {
        $passed = false;
    }
    assertTrue(
        $passed
        ,'environment : PHP/server does not auto-HTTP-encode content'
    );
    fclose($fp);
    
    if (__FILE__ === realpath($_SERVER['SCRIPT_FILENAME'])) {
        if (! $passed) {
            echo "\nReturned content should be 6 bytes and not HTTP encoded.\n"
               . "Headers returned by: {$thisUrl}?hello=1\n\n";
            var_export($meta['wrapper_data']);
        }
    }
}

test_environment();