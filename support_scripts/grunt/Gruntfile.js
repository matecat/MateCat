module.exports = function(grunt) {
    var basePath = '../../public/js/';
    var buildPath = '../../public/js/build/';
    var incPath = '../../inc/';

    var cssFiles = [
        basePath + '../css/common.css',
        basePath + '../css/style.css',
        basePath + '../css/mbc-style.css',
        basePath + '../css/segment-notes.css',
        basePath + '../css/project-completion-feature.css',
        basePath + '../css/editlog.css',
        basePath + '../css/review_improved.css'
    ];

    var sassFiles = [
        basePath + '../css/review_improved.scss'
    ];

    var conf = grunt.file.read( incPath + 'version.ini' );
    var version = conf.match(/version[ ]+=[ ]+.*/gi)[0].replace(/version[ ]+=[ ]+(.*?)/gi, "$1");
    grunt.log.ok( 'Matecat Version: ' + version );

    function stripPrefixForTempaltes(filePath) {
        /**
         * Strip '../../public/js/cat_source/templates/'
         * from template identifiers.
         */
        var dirsToStrip = 6 ;
        var strippedPath = filePath.split('/')
        .splice( dirsToStrip ).join('/')
        .replace('.hbs', '') ;

        return strippedPath ;
    }

    // Configuration goes here
    grunt.initConfig({
        handlebars : {
            options : {
                namespace : 'MateCat.Templates',
                processPartialName: stripPrefixForTempaltes ,
                processName: stripPrefixForTempaltes
            },
            all : {
                files : {
                    '../../public/js/build/templates.js' : [
                        basePath + 'cat_source/templates/**/*.hbs'
                    ]
                }
            }
        },
        concat: {
            components: {
                src: [
                    basePath + 'build/templates.js',

                    basePath + 'cat_source/ui.core.js',
                    basePath + 'cat_source/ui.segment.js',
                    basePath + 'cat_source/ui.scrollsegment.js',
                    basePath + 'cat_source/ui.classes.js',
                    basePath + 'cat_source/ui.classes.segment_footer.js',
                    basePath + 'cat_source/ui.init.js',
                    basePath + 'cat_source/ui.render.js',
                    basePath + 'cat_source/ui.events.js',
                    basePath + 'cat_source/ui.contribution.js',
                    basePath + 'cat_source/ui.tags.js',
                    basePath + 'cat_source/ui.concordance.js',
                    basePath + 'cat_source/ui.glossary.js',
                    basePath + 'cat_source/ui.search.js',

                    basePath + 'cat_source/functions.js', // TODO: why this depends on this position?

                    basePath + 'cat_source/ui.customization.js',
                    basePath + 'cat_source/ui.review.js',
                    basePath + 'cat_source/ui.offline.js',
                    basePath + 'cat_source/ui.split.js',
                    basePath + 'cat_source/ui.opensegment.js',

                    basePath + 'cat_source/sse.js',
                    basePath + 'cat_source/mbc.main.js',
                    basePath + 'cat_source/mbc.templates.js',

                    basePath + 'cat_source/project_completion.*.js',
                    basePath + 'cat_source/segment_notes.*.js',
                    basePath + 'cat_source/review_improved.*.js',

                    basePath + 'tm.js'
                ],
                dest: buildPath + 'cat.js'
            },
            libraries: {
                src: [
                    basePath + 'lib/lodash.min.js',
                    basePath + 'lib/handlebars.runtime-v4.0.5.js',
                    basePath + 'lib/jquery-1.11.0.min.js',
                    basePath + 'lib/waypoints.min.js',
                    basePath + 'lib/jquery-ui.js',
                    basePath + 'lib/jquery.hotkeys.min.js',
                    basePath + 'lib/jquery.cookie.js',
                    basePath + 'lib/jquery.tablesorter-fork-mottie.js',
                    basePath + 'lib/jquery.tooltipster.min.js',
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
                    basePath + '../css/mbc-style.css',
                    basePath + '../css/segment-notes.css',
                    basePath + '../css/project-completion-feature.css',
                    basePath + '../css/review_improved.css'
                ],
                dest: basePath + '../css/app.css'
            }
        },
        watch: {
            js: {
                files: [
                    basePath + 'cat_source/templates/**/*.hbs',
                    basePath + 'cat_source/*.js',
                    basePath + 'tm.js',
                ],
                tasks: ['development:js'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            },
            css: {
                files: cssFiles.concat( sassFiles ),
                tasks: ['development:css'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            }
        },
        sass: {
            dist: {
                files: [{
                    expand: true,
                    // cwd: basePath,
                    src: sassFiles,
                    dest: basePath + '../css/',
                    ext: '.css'
                }]
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
    grunt.loadNpmTasks('grunt-contrib-handlebars');
    grunt.loadNpmTasks('grunt-contrib-sass');

    // Define your tasks here
    grunt.registerTask('default', ['jshint']);

    grunt.registerTask('development:js', [
        'concat:libraries',
        'handlebars', 'concat:components', 'replace:version',
        'concat:app'
    ]);

    grunt.registerTask('development:css', ['sass', 'concat:styles'] );

    grunt.registerTask('development', [
        'development:js', 'development:css'
    ]);

    grunt.registerTask('deploy', [
        'concat:libraries', 'handlebars', 'concat:components', 'replace:version',
        'concat:app', 'concat:styles',
        'strip'
    ]);
};


