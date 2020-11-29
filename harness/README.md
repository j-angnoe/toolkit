# Harness

Quick prototyping, create a directory, create some html, php and stylesheets
and fiddle with your prototype in the browser.

Your prototype may make use of Vue, VueRouter, vue-blocks and it will contain
an api bridge to call functions on your defined php objects.
## Example

```php 
<!-- myprotoype/index.html -->
<template url="/">
    <div>
        <h1>My Prototype</h1>
        <p>Retrieved data:</p>
        <pre>{{serverData}}</pre>
    </div>
    <script>
        export default {
            async mounted() {
                var arg1 = 'arg1';
                var arg2 = 'some value';
                this.serverData = await api.controller.getMyData(arg1, arg2);
            }
        }
    </script>
</template>

<?php 
class controller {
    function getMyData($arg1, $arg2) {
        return [
            'you have sent us: ' . $arg1 . ' and ' . $arg2
        ];
    }
}
?>
```

## Installation

```sh
# Get the harness executable
wget https://github.com/j-angnoe/toolkit/raw/master/harness/build/harness.phar

# Make it executable
chmod +x harness.phar

# Create a symlink ins /usr/local/bin so you can access it anywhere from the commandline
sudo ln -sf harness.phar /usr/local/bin/harness 
```

## Usage 


```sh 
# Spin up a webserver that will serve your directory
harness [directory]  

# Execute a controller method
harness exec [controller] [method] [...args]
harness exec [filename] [method] [...args]
harness run  # alias for exec

# Building `bundle.js` files for your tools
npm install -g parcel;      # Parcel is a nice companion for prototyping
cd my-tool;
harness build .     # one time build
harness watch .     # continious building 

# Creating a harness tool
harness init my-tool;
cd my-tool;
# start working on your tool

# Harnass options 
harnass [directory]
    --docker      # Run the tool inside a matching docker-container
    --docker=serviceName # Run the tool inside the this service.
    --dockerfile    # Specify a dockerfile / path containing a dockerfile.
    --port        # Which port to run
    --no-browser  # Dont open a browser window
    --tool        # Specify tool directory (instead of assuming [directory] is a tool)
```

## Harness inside docker
Sometimes your tool requires to be run inside a context of a container.
Now you can add the --docker 
Assumes you have docker-compose and assumes that you run harness inside.

## Requirements
- firefox   (for triggering the browser)
- the fd command
- parcel (npmjs.org/parcel) for automagic bundle building


