<?php

class launcher {
    function editor($path) {
        system("code $path");
    }

    function terminal($path) {
        system("terminator --working-directory=$path");
    }

    function runCommand($path, $command) {
        $id = substr(sha1(microtime(true) . uniqid()), 0, 8);
        $dir = '/tmp/project-master/run-commands/' . $id;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $sleep = "echo; echo 'press enter to close'; read x;";

        file_put_contents("$dir/script.sh", "cd $path; $command; $sleep");

        system("chmod +x $dir/script.sh");
        
        // @configurable 
        system("gnome-terminal -- $dir/script.sh &");
        // system("terminator -x $dir/script.sh");
        // @endconfigurable 
    }
}
