# Personal Tools

Developer Tools, prototypes and stuff for making tools and prototypes.

## Tools 
### [Crude](./crude)
A database table manager / companion I've been using for years in one way or another.

### [Exec-tool](./exec-tool)
Quickly execute code inside the project, but this time via an external tool instead of embedded in the project.

### [Vise](./vise)
Clamp down on your code and enjoy the tighest feedback loop you could imagine.

## Stuff for making tools
### [Harness](./harness)
A good start is half the work. But before you can really start building your prototype
you usually have to write a lot of boilerplate, installing dependencies, searching and collecting 
useful bits and pieces you wrote earlier and copy-paste. 

Harness includes a webserver and a simple router that will serve your tool on a free port,
it scans all files, serves html and it provides an 'Api Bridge', so you can call methods on 
server side objects in javascript. So you can focus on writing your prototype instead of 
writing a application foundation again.

An example of a simple single-page tool, a calculator:
```php
<?php
    class my_calculator {
        function calculate($a, $b) {
            return $a + $b;
        }
    }
?>
<template url="/">
    <div>
        <h1>My calculator</h1>
        <form @submit.prevent="performCalcution()">
            <div><input type="number" v-model="a"> + <input type="number" v-model="b"> = {{result}}</div>
            <br>
            <input type="submit">
        </form>
    </div>
    <style scoped>
    input[type="number"] {
        width: 80px;
        padding: 10px;
    }
    </style>
    <script>
    'short';
    return class {
        a = null;
        b = null;
        result = null;
        
        async performCalcution() {
            this.result = await api.my_calculator.calculate(this.a, this.b);
        }
    }
    </script>
</template>
```

![](docs/images/2020-11-29-00-40-32.png)

To run it:
`harness sample-calculator`

This example demonstrates:
- Harness serves the tool
- An api bridge allows you to run method `calculate` of the `my_calculator` class, it marshalles
    the arguments returns you the exact same result as your function returned.
- The <template url="/"> is a vue-blocks way of defining the page component for /
- The 'short' syntax allows us to write less Vue Component boilerplate.
- The default harness supplies us bootstrap and a bit of background color.

### [The default harness](./vue-default-harness)
Each tool will have access to stuff inside your default harness. The default harness is the
place to put stuff you use the most: php functions and classes, components, javascripts, etc.

My default harness 'vue-default-harness' includes bootstrap-css, vue, vue-blocks and some php functions
I use the most. 




