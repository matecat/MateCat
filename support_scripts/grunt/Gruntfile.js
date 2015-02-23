module.exports = function(grunt) {
	var basePath = '../../public/js/';
	var incPath = '../../inc/';
	var conf = grunt.file.read(incPath + 'config.inc.sample.php');
	var version = conf.match(/self\:\:\$BUILD\_NUMBER = \'(.*?)\'/gi)[0].replace(/self\:\:\$BUILD\_NUMBER = \'(.*?)\'/gi, "$1");

	
  // Configuration goes here
	grunt.initConfig({
		concat: {
			components: {
				src: [
					basePath + 'cat_source/ui.core.js', 
					basePath + 'cat_source/ui.init.js',
					basePath + 'cat_source/ui.render.js',
					basePath + 'cat_source/ui.events.js',
					basePath + 'cat_source/ui.contribution.js',
					basePath + 'cat_source/ui.tags.js',
					basePath + 'cat_source/ui.concordance.js',
					basePath + 'cat_source/ui.glossary.js',
					basePath + 'cat_source/ui.search.js',
					basePath + 'cat_source/functions.js',
					basePath + 'cat_source/ui.customization.js',
                    basePath + 'cat_source/ui.review.js',
                    basePath + 'tm.js',
                    basePath + 'cat_source/ui.offline.js',
                    basePath + 'cat_source/ui.noconnection.js'
				],
				dest: basePath + 'cat.js'
			},
			libraries: {
				src: [
					basePath + 'lib/jquery-1.11.0.min.js',
//					basePath + 'lib/jquery-migrate-1.2.1.js',
					basePath + 'lib/jquery.cookie.js',
                    basePath + 'lib/jquery.hotkeys.min.js',
                    basePath + 'lib/jquery.dataTables.min.js',
					basePath + 'lib/diff_match_patch.js',
					basePath + 'lib/rangy-core.js',
					basePath + 'lib/rangy-selectionsaverestore.js',
					basePath + 'lib/snapengage.js',
					basePath + 'lib/waypoints.js',
				],
				dest: basePath + 'libs.js'
			},
			app: {
				src: [
					basePath + 'libs.js',
					basePath + 'common.js',
					basePath + 'cat.min.js',
					basePath + 'logout.js'
				],
				dest: basePath + 'app.js'
			},
			styles: {
				src: [
					basePath + '../css/common.css',
//					basePath + '../css/jquery-ui.css',
					basePath + '../css/style.css'
				],
				dest: basePath + '../css/app.css'
			}
		},
		watch: {
			scripts: {
				files: [basePath + 'cat_source/*.js', basePath + 'tm.js'],
				tasks: ['dev-watch'],
				options: {
					interrupt: true
				}
			},
		},
		jshint: {
			options: {
			  force: true,
			  smarttabs: true
			},
			all: [basePath + 'cat_source/*.js']
		},
		uglify: {
			options: {
				banner: "",
				compress: true,
				mangle: true
			},
			build: {
				src: basePath + 'cat.js',
				dest: basePath + 'cat.min.js'
			}
		},
		removelogging: {
			dist: {
				src: basePath + "cat.min.js",
				dest: basePath + "cat.min.js"
			}
		},
		replace: {
		  example: {
			src: [basePath + 'cat.js'],             // source files array (supports minimatch)
			dest: basePath + 'cat.js',             // destination directory or file
			replacements: [{
			  from: /this\.version \= \"(.*?)\"/gi,      // regex replacement ('Fooo' to 'Mooo')
			  to: 'this.version = "' + version + '"'
			}]
		  }
		}
	});



  // Load plugins here
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-remove-logging');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-text-replace');
//	grunt.loadNpmTasks('grunt-contrib-jasmine');
	
	
  // Define your tasks here
//	grunt.registerTask('default', ['concat']);
//	grunt.registerTask('dev-watch', ['concat', 'uglify', 'removelogging']);
//	grunt.registerTask('dev-watch', ['jshint', 'concat:libraries', 'concat:components', 'uglify', 'concat:app']);
//	grunt.registerTask('dev-watch', ['jshint', 'concat:libraries', 'concat:components', 'uglify', 'concat:app', 'concat:styles']);
	grunt.registerTask('dev-watch', ['jshint', 'concat:components', 'replace:example', 'uglify', 'concat:app', 'concat:styles']);
};


