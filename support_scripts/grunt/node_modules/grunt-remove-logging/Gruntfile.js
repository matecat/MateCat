module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    nodeunit: {
      all: ["test/**/*.js"]
    },
    watch: {
      files: "<config:lint.files>",
      tasks: "default"
    },
    jshint: {
      all: ["grunt.js", "tasks/**/*.js", "test/**/*.js"],
      options: {
        curly: true,
        eqeqeq: true,
        immed: true,
        latedef: true,
        newcap: true,
        noarg: true,
        sub: true,
        undef: true,
        boss: true,
        eqnull: true,
        node: true,
        globals: {}
      }
    }
  });

  grunt.loadNpmTasks("grunt-contrib-jshint");
  grunt.loadNpmTasks("grunt-contrib-nodeunit");

  grunt.registerTask("default", ["jshint", "nodeunit"]);
};
