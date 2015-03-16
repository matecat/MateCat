module.exports = function(grunt) {
	var basePath = '../../../public/js/';
	var incPath = '../../../inc/';
	var conf = grunt.file.read(incPath + 'config.inc.sample.php');

  // Configuration goes here
	grunt.initConfig({
		replace: {
            config: {
                src: [incPath + 'config.inc.sample.php'],       // source files array (supports minimatch)
                dest: incPath + 'config.inc.php',       // destination directory or file

                replacements: [
                    {
                     from: /self::\$DB_SERVER(.*?)\n(.*?)\n(.*?)\n(.*?)password/gi,      // regex replacement ('Fooo' to 'Mooo')
                     to:     '\n/*\n' +
                            '        self::$DB_SERVER   = "10.30.1.250"; //database server\n' +
                            '        self::$DB_DATABASE = "matecat_sandbox"; //database name\n' +
                            '        self::$DB_USER     = "matecat"; //database login\n' +
                            '        self::$DB_PASS     = "matecat01"; //dase password\n' +
                            '*/\n\n' +

                            '        // db di Domenico\n\n' +

                            '        self::$DB_SERVER   = "10.3.15.96"; //database server\n' +
                            '        self::$DB_DATABASE = "matecat"; //database name\n' +
                            '        self::$DB_USER     = "matecat_user"; //database login\n' +
                            '        self::$DB_PASS     = "matecat_user"; //database password\n\n'
                    },
                    {

                    }
                ]

            }
		}
	});



  // Load plugins here
	grunt.loadNpmTasks('grunt-text-replace');
//	grunt.loadNpmTasks('grunt-contrib-jasmine');
	
	
  // Define your tasks here
    grunt.registerTask('update-config', ['replace:config']);
};


