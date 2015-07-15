module.exports = function(grunt) {
	var basePath = '../../public/js/';
    var buildPath = '../../public/js/build/';
	var incPath = '../../inc/';

    var cssFiles = [
        basePath + '../css/common.css',
        basePath + '../css/style.css',
        basePath + '../css/mbc-style.css'
    ]

    var conf = grunt.file.read( incPath + 'version.ini' );
    var version = conf.match(/version[ ]+=[ ]+.*/gi)[0].replace(/version[ ]+=[ ]+(.*?)/gi, "$1");
    grunt.log.ok( 'Matecat Version: ' + version );

    // Configuration goes here
	grunt.initConfig({
		concat: {
			components: {
				src: [
					basePath + 'cat_source/ui.core.js',
					basePath + 'cat_source/ui.scrollsegment.js',
					basePath + 'cat_source/ui.classes.js',
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
                    basePath + 'cat_source/ui.offline.js',
                    basePath + 'cat_source/ui.split.js',
                    basePath + 'cat_source/sse.js',
                    basePath + 'cat_source/mbc.main.js',
                    basePath + 'cat_source/mbc.templates.js',
                    basePath + 'tm.js'
				],
				dest: buildPath + 'cat.js'
			},
			libraries: {
				src: [
					basePath + 'lib/jquery-1.11.0.min.js',
                    basePath + 'lib/waypoints.min.js',
                    basePath + 'lib/jquery-ui.js',
                    basePath + 'lib/jquery.hotkeys.min.js',
                    basePath + 'lib/jquery.cookie.js',
                    basePath + 'lib/jquery.tablesorter-fork-mottie.js',
					basePath + 'lib/diff_match_patch.js',
					basePath + 'lib/rangy-core.js',
					basePath + 'lib/rangy-selectionsaverestore.js'
				],
				dest: buildPath + 'libs.js'
			},
			app: {
				src: [
					basePath + 'common.js',
					buildPath + 'cat.js',
					basePath + 'logout.js'
				],
				dest: buildPath + 'app.js'
			},
			styles: {
				src: [
					basePath + '../css/common.css',
					basePath + '../css/style.css',
					basePath + '../css/mbc-style.css'
				],
				dest: basePath + '../css/app.css'
			}
		},
		watch: {
			scripts: {
                files: [
                    basePath + 'cat_source/*.js',
                    basePath + 'tm.js',
                ].concat( cssFiles ) ,

				tasks: ['development'],
				options: {
					interrupt: true
				}
			}
		},
		jshint: {
			options: {
			  force: true,
			  smarttabs: true
			},
			all: [basePath + 'cat_source/*.js'] // TODO: expand to other js files
		},
        strip : {
            app : {
                src : buildPath + 'app.js',
                options : {
                    inline : true,
                    nodes : ['console.log']
                }
            }
        },
        comments: {
            app: {
                // Target-specific file lists and/or options go here.
                options: {
                    singleline: true,
                    multiline: true
                },
                src: [ buildPath + 'app.js' ] // files to remove comments from
            },
        },
		replace: {
		  version: {
			src: [buildPath + 'cat.js'],             // source files array (supports minimatch)
			dest: buildPath + 'cat.js',             // destination directory or file
			replacements: [{
			  from: /this\.version \= \"(.*?)\"/gi,      // regex replacement ('Fooo' to 'Mooo')
			  to: 'this.version = "' + version + '"'
			}]
		  }
		}
	});

	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-text-replace');
    grunt.loadNpmTasks('grunt-strip');
    grunt.loadNpmTasks('grunt-stripcomments');

    // Define your tasks here
    grunt.registerTask('default', ['jshint']);

    grunt.registerTask('development', [
        'jshint',
        'concat:libraries', 'concat:components', 'replace:version',
        'concat:app', 'concat:styles'
    ]);

    grunt.registerTask('deploy', [
        'concat:libraries', 'concat:components', 'replace:version',
        'concat:app', 'concat:styles',
        'comments', // strips comments
        'strip' // strips console.log
    ]);
};


