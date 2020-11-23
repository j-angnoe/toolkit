<?php

function findClosestFile($filename, $path = null) 
{
    // paths from .git, package.json, composer.json

    $tryFiles = !is_array($filename) ? [$filename] : $filename;
    // print_R($tryFiles);

    $currentPath = realpath($path) ?: getcwd() . "/" . $path;

    while($currentPath > '/home' && $currentPath > '/') {
        // echo $currentPath . "\n";
        foreach ($tryFiles as $file) {
            // echo "$currentPath/$file\n";

            if (is_dir($currentPath . "/" . $file) || is_file($currentPath . "/" . $file)) {
                return $currentPath . '/' . $file;
            }

        }    
        $currentPath = dirname($currentPath);
    }
    return false;
}

