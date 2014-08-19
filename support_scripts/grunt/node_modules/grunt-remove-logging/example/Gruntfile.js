/*global module:false*/
module.exports = function(grunt) {
  "use strict";

  grunt.loadTasks("../tasks");

  grunt.initConfig({
    removelogging: {
      dist: {
        src: "js/app.js",
        dest: "js/output.js"
      }
    }
  });

  grunt.registerTask("default", ["removelogging"]);
};
