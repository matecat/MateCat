/*
 * Grunt Remove Logging
 * https://github.com/ehynds/grunt-remove-logging
 *
 * Copyright (c) 2013 Eric Hynds
 * Licensed under the MIT license.
 */

module.exports = function(grunt) {
  "use strict";

  var task = require("./lib/removelogging").init(grunt);

  grunt.registerMultiTask("removelogging", "Remove console logging", function() {
    var opts = this.options();

    var process = function(srcFile) {
      var result = task(grunt.file.read(srcFile), opts);
      grunt.log.writeln("Removed " + result.count + " logging statements from " + srcFile);
      return result;
    };

    this.files.forEach(function(f) {
      if(typeof f.dest === "undefined") {
        f.src.forEach(function(srcFile) {
          var result = process(srcFile);
          grunt.file.write(srcFile, result.src);
        });
      } else {
        var ret = f.src.map(function(srcFile) {
          if(grunt.file.isFile(srcFile)){
            return process(srcFile).src;
          } else {
            grunt.log.error("File not found " + srcFile);
          }
        }).join("");

        if(ret) {
          grunt.file.write(f.dest, ret);
        }
      }
    });
  });
};
