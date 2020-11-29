<?php

class PharaoBuild {
    var $packageJson;
    var $packageData;

    function __construct($path) {
        $package = findClosestFile('package.json', $path);

        $this->packageJson = realpath($package);

        
        chdir(dirname($package));

        $this->read();
    }
    function read() {
        $this->data = null;
        $this->packageData = [];

        if ($this->packageJson) {
            $data = read_json($this->packageJson);
        }

        $data['pharao'] = $data['pharao'] ?? [];

        $this->packageData = $data;
        $this->data = &$this->packageData['pharao'];

    }

    function get_grep_terms($term) {
        $term = trim($term);
        $greps = [];
        foreach (preg_split('~\s+~', $term) as $t) {
            if (substr($t, 0, 1) === '-') {
                $greps[] = 'grep -vi '.escapeshellarg(substr($t,1));
            } else {
                $greps[] = 'grep -i ' . escapeshellarg($t);
            }
        }
        return $greps;
    }

    function list_files($patterns = null) {
        $patterns = $patterns ?? $this->data['files'] ?? [];
        foreach ($patterns as $p) {
            if (!trim($p)) {
                continue;
            }
            $p = '^./' . ltrim($p, '^./');
            $greps = join(" | ", $this->get_grep_terms($p));
            foreach (command("find . -type f | $greps") as $file) {
                // Clear the ./ from the directory';
                yield ltrim($file, './');
            }
        } 
    }

    function build_phar() {
        $settings = $this->data;

        $files = $settings['files'];
        $output = $settings['output'];
        $entrypoint = $settings['entrypoint'] ?? 'index.php';

        if (!is_dir(dirname($output))) {
            mkdir(dirname($output), 0777, true);
        }

        if (file_exists($output)) {
            unlink($output);
        }

        $phar = new Phar($output);

        $phar->startBuffering();
        foreach ($this->list_files($files) as $file) {
            echo "Adding file $file\n";
            $phar->addFile(realpath($file), $file);
        }
        $entrypointCli = is_array($entrypoint) ? $entrypoint['cli'] : $entrypoint;
        $entrypointWeb = is_array($entrypoint) ? $entrypoint['web'] : $entrypoint;

        $defaultStub = $phar->createDefaultStub($entrypointCli, $entrypointWeb);
        $stub = "#!/usr/bin/php \n" . $defaultStub;
        $phar->setStub($stub);
        $phar->stopBuffering();
        $phar->compressFiles(Phar::GZ);
        `chmod +x $output`;

        echo "Created phar file $output\n";
        echo `ls -lah $output\n`;
        $phar = null;
    }

}


if (!function_exists('findClosestFile')) { 
    /**
     * Super handy function to search for the closest
     * file given some path.
     * 
     * findClosestFile('package.json', '/path/to/my/project/app/some/folder')
     * might return /path/to/my/project/package.json
     */
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
}

if (!function_exists('read_json')) { 
    function read_json($file, $asObjects = false) {
        return json_decode(file_get_contents($file), $asObjects ? 0 : 1);
    }
}

if (!function_exists('write_json')) { 
    function write_json($file, $data) {
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('command')) { 
    /**
     * Command: Run a shell command and return lines as an array.
     */
    function command($command) {
        return array_filter(explode("\n", trim(shell_exec($command))));
    }
}
