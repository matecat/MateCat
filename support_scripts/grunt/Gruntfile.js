module.exports = function(grunt) {
    var mapFilePath;

    var basePath = '../../public/js/';
    var buildPath = '../../public/js/build/';
    var incPath = '../../inc/';
    var cssBase = '../../public/css/';

    var conf = grunt.file.read( incPath + 'version.ini' );
    var version = conf.match(/version[ ]+=[ ]+.*/gi)[0].replace(/version[ ]+=[ ]+(.*?)/gi, "$1");
    grunt.log.ok( 'Matecat Version: ' + version );

    var cssWatchFiles = [
        cssBase + 'common.css',
        cssBase + 'style.css',
        cssBase + 'mbc-style.css',
        cssBase + 'segment-notes.css',
        cssBase + 'project-completion-feature.css',
        cssBase + 'editlog.css',
        cssBase + 'review_improved.css' ,
        cssBase + 'lib/remodal.css',
        cssBase + 'lib/remodal-default-theme.css',
        cssBase + 'sass/review_improved.scss',
    ];

    function s4() {
        return Math.floor((1 + Math.random()) * 0x10000)
        .toString(16).substring(1);
    }

    function stripPrefixForTemplates(filePath) {
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
                processPartialName: stripPrefixForTemplates ,
                processName: stripPrefixForTemplates
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
            app: {
                options: {
                    sourceMap: true,
                    sourceMapName: function() {
                        var path = buildPath + '/app.*.source-map.js';
                        var expanded = grunt.file.expand( path );

                        expanded.forEach( function( item ) {
                            grunt.log.ok( 'deleting previous source map: ' + item );
                            grunt.file.delete( item, { force : true }  );
                        });

                        return buildPath + '/app.' + s4() + '.source-map.js' ;
                    }
                },
                src: [
                    basePath + 'common.js',
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
                    basePath + 'cat_source/review_improved.js',
                    basePath + 'cat_source/review_improved.*.js',
                    basePath + 'cat_source/handlebars-helpers.js',

                    basePath + 'tm.js',
                    basePath + 'logout.js'
                ],
                dest: buildPath + 'app.js'
            },
            libraries: {
                src: [
                    basePath + 'lib/lodash.min.js',
                    basePath + 'lib/moment.min.js',
                    basePath + 'lib/handlebars.runtime-v4.0.5.js',
                    basePath + 'lib/jquery-1.11.0.min.js',
                    basePath + 'lib/remodal.min.js',
                    basePath + 'lib/waypoints.min.js',
                    basePath + 'lib/jquery-ui.js',
                    basePath + 'lib/jquery.hotkeys.min.js',
                    basePath + 'lib/jquery.cookie.js',
                    basePath + 'lib/jquery.tablesorter-fork-mottie.js',
                    basePath + 'lib/jquery.tooltipster.min.js',
                    basePath + 'lib/diff_match_patch.js',
                    basePath + 'lib/rangy-core.js',
                    basePath + 'lib/rangy-selectionsaverestore.js',
                    basePath + 'lib/lokijs.min.js',
                    basePath + 'lib/sprintf.min.js',
                ],
                dest: buildPath + 'libs.js'
            },
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
                files: cssWatchFiles ,
                tasks: ['development:css'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            }
        },
        sass: {
            dist: {
                options : {
                    sourceMap : true,
                    includePaths: [ cssBase, cssBase + 'libs/' ]
                },
                files: {
                    '../../public/css/app.css' :
                        '../../public/css/sass/main.scss'
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
        replace: {
          version: {
            src: [
                buildPath + 'app.js'
            ],
            dest: buildPath + 'app.js',
            replacements: [{
              from: /this\.version \= \"(.*?)\"/gi,
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
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-browserify');

    // Define your tasks here
    grunt.registerTask('default', ['jshint']);

    grunt.registerTask('development:js', [
        'handlebars',
        'concat:libraries',
        'concat:app',
        'replace:version',
    ]);

    grunt.registerTask('development', [
        'development:js', 'sass'
    ]);

    grunt.registerTask('deploy', [
        'handlebars',
        'concat:libraries',
        'concat:app',
        'replace:version',
        'strip',
        'sass',  // <-- TODO rename this
    ]);
};


