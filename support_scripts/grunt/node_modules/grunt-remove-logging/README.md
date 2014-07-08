## About

This task removes all console logging statements from your source code.

## Getting Started

Install this plugin with the command:

```js
npm install grunt-remove-logging
```

Next, add this line to your project's grunt file:

```js
grunt.loadNpmTasks("grunt-remove-logging");
```

Lastly, add the configuration settings (see below) to your grunt file.

## Documentation

This task has two required properties, `src` and `dest`. `src` is the path to
your source file and `dest` is the file this task will write to (relative to the
grunt.js file).

An example configuration looks like this:

```js
grunt.initConfig({
  removelogging: {
    dist: {
      src: "js/application.js",
      dest: "js/application-clean.js",

      options: {
        // see below for options. this is optional.
      }
    }
  }
});
```

To run this task against multiple files and **automatically overwrite them**
with the resultant output, omit the `dist` option:

```js
grunt.initConfig({
  removelogging: {
    dist: {
      src: "dist/**/*.js" // Each file will be overwritten with the output!
    }
  }
});
```

### Optional Configuration Properties

This plugin can be customized by specifying the following options:

* `replaceWith`: A value to replace logging statements with. This option defaults to an empty string. If you use fancy statements like `console && console.log("foo");`, you may choose to specify a `replaceWith` value like `0;` so that your scripts don't completely break.
* `namespace`: An array of object names that logging methods are attached to.
Defaults to `[ 'console', 'window.console' ]`. If you use a custom logger, like
`MyApp.logger.log(foo)`, you would set this option to `[MyApp.logger]`.
* `methods`: An array of method names to remove. Defaults to [all the methods](http://getfirebug.com/wiki/index.php/Console_API) in the Firebug console API. This option is useful if you want to strip out all `log` methods, but keep `warn` for example.

### Skipping Individual Statements

You can tell this task to keep specific logging statements by adding the comment directive `/*RemoveLogging:skip*/` after the statement:

```js
console.log("foo");/*RemoveLogging:skip*/

// or:

console.log("foo")/*RemoveLogging:skip*/;

// whitespace is fine too, whatever floats your boat:

console.log("foo") /* RemoveLogging:skip */;
```
