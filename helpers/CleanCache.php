<?php


include __DIR__."/../bootstrap.php";

function main()
{
    // echo "Clean logs..." . PHP_EOL;
    $files = glob(__DIR__.'/../storage/cache/*'); // get all file names
    foreach ($files as $file) { // iterate files
        
        if (is_file($file)) {
            unlink($file); // delete file
        }
    }
}

main();
