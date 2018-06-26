module.exports = function(grunt) {
    var mapFilePath;

    var basePath = '../../public/js/';
    var gruntDir = '../../support_scripts/grunt/';
    var buildPath = '../../public/js/build/';
    var buildPathCss = '../../public/css/build/';
    var incPath = '../../inc/';
    var cssBase = '../../public/css/';

    var cssFiles = [
        basePath + '../css/common.css',
        basePath + '../css/style.css',
        basePath + '../css/mbc-style.css',
        basePath + '../css/segment-notes.css',
        basePath + '../css/project-completion-feature.css',
        basePath + '../css/editlog.css',
	    basePath + '../css/lxq-style.css',
	    basePath + '../css/lexiqa.css',
    ];

    var conf = grunt.file.read( incPath + 'version.ini' );
    var version = conf.match(/version[ ]+=[ ]+.*/gi)[0].replace(/version[ ]+=[ ]+(.*?)/gi, "$1");
    grunt.log.ok( 'Matecat Version: ' + version );

    var cssWatchFiles = [
        cssBase + 'sass/variables.scss',
        cssBase + 'common.css',
        cssBase + 'style.css',
        cssBase + 'mbc-style.css',
        cssBase + 'segment-notes.css',
        cssBase + 'project-completion-feature.css',
        cssBase + 'editlog.css',
	    cssBase + 'jquery.powertip.min.css',
	    cssBase + 'lxq-style.css',
	    cssBase + 'lexiqa.css',
        cssBase + 'sass/review_improved.scss',
        cssBase + 'sass/segmentsFilter.scss',
        cssBase + 'sass/cattool.scss',
        cssBase + 'sass/speech2text.scss',
        cssBase + 'sass/notifications.scss',
        cssBase + 'sass/commons/*.scss',
        cssBase + 'sass/vendor_mc/*',
        cssBase + '../holidays/*.css'
    ];
    var cssWatchFilesUploadPage = [
        cssBase + 'sass/variables.scss',
        cssBase + 'common.css',
        cssBase + 'upload-page.scss',
        cssBase + 'popup.css',
        cssBase + 'sass/notifications.scss'
    ];


    var cssWatchManage = [
        cssBase + 'sass/commons/*'
    ];




    var es2015Preset = require('babel-preset-es2015');
    var reactPreset = require('babel-preset-react');

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
    grunt.initConfig( {
        handlebars: {
            options: {
                namespace: 'MateCat.Templates',
                processPartialName: stripPrefixForTemplates,
                processName: stripPrefixForTemplates
            },
            all: {
                src : [
                    basePath + 'cat_source/templates/**/*.hbs'
                ],
                dest : buildPath + 'templates.js'
            }
        },

        /**
         * = Browseriry
         *
         * This is the new build process based on browserify
         * to take advantage of transforms, plugins and import
         * and require syntax to declare dependencies.
         * Use this for current / future development.
         *
         * All imports to be attached to window should be defined in
         * the entry point js file.
         *
         *
         */

        browserify: {
            libs: {
                options: {
                    transform: [
                        [ 'babelify', { presets: [ es2015Preset, reactPreset ] } ]
                    ],
                    browserifyOptions: {
                        paths: [ __dirname + '/node_modules' ]
                    }
                },
                src: [
                    basePath + 'cat_source/es6/react-libs.js'
                ],
                dest: buildPath + 'react.js'
            },
            components: {
                options: {
                    transform: [
                        [ 'babelify', { presets: [ es2015Preset, reactPreset ] } ]
                    ],
                    browserifyOptions: {
                        paths: [ __dirname + '/node_modules' ]
                    }
                },
                src: [
                    basePath + 'cat_source/es6/react/*.js'
                ],
                dest: buildPath + 'cat-react.js'
            },
            qaReportsVersions: {
                options: {
                    transform: [
                        [ 'babelify', { presets: [ es2015Preset, reactPreset ] } ]
                    ],
                    browserifyOptions: {
                        paths: [ __dirname + '/node_modules' ]
                    }
                },
                src: [
                    basePath + 'cat_source/es6/react/components/review_improved/review_improved.qa_report.js',
                ],
                dest: buildPath + 'qa-report-improved.js'
            },
            test: {
                options: {
                    external: [
                        'react/addons',
                        'react/lib/ReactContext',
                        'react/lib/ExecutionEnvironment'
                    ],
                    transform: [
                        [ 'babelify', { presets: [ es2015Preset, reactPreset ] } ]
                    ],
                    browserifyOptions: {
                        paths: [ __dirname + '/node_modules' ]
                    }
                },
                src: [
                    basePath + '__tests__/*.js',
                ],
                dest: buildPath + '/tests/test-react.js'
            },
        },

        //


        /**
         *
         * = Concat
         *
         * This is pure concatenation of files, deprecated in favour of
         * browserify. Everything here should be migrated sooner or later.
         *
         * This concat makes use of a file generated by the `browserify`
         * step, where we process es6 code, react, and import libraries.
         *
         */
        concat: {
            app: {
                options : {
                    sourceMap: false,
                    sourceMapName: function () {
                        var path = buildPath + '/app.*.source-map.js';
                        var expanded = grunt.file.expand( path );

                        expanded.forEach( function ( item ) {
                            grunt.log.ok( 'deleting previous source map: ' + item );
                            grunt.file.delete( item, {force: true} );
                        } );

                        return buildPath + '/app.' + s4() + '.source-map.js';
                    }
                },
                src: [
                    basePath + 'build/templates.js',
                    basePath + 'cat_source/ui.core.js',
                    basePath + 'cat_source/ui.segment.js',
                    basePath + 'cat_source/ui.editarea.js',
                    basePath + 'cat_source/ui.scrollsegment.js',
                    basePath + 'cat_source/ui.classes.js',
                    basePath + 'cat_source/ui.init.js',
                    basePath + 'cat_source/ui.render.js',
                    basePath + 'cat_source/ui.events.js',
                    basePath + 'cat_source/ui.contribution.js',
                    basePath + 'cat_source/ui.tags.js',
                    basePath + 'cat_source/ui.concordance.js',
                    basePath + 'cat_source/ui.glossary.js',

                    basePath + 'cat_source/qa_check_glossary.js',
                    basePath + 'cat_source/qa_check_blacklist.js',

                    basePath + 'cat_source/functions.js', // TODO: why this depends on this position?
                    basePath + 'cat_source/utils.js', // TODO: why this depends on this position?

                    basePath + 'cat_source/es6/react/ajax_utils/segmentUtils.js',

                    basePath + 'cat_source/ui.customization.js',
                    // basePath + 'cat_source/ui.review.js',
                    basePath + 'cat_source/ui.offline.js',
                    basePath + 'cat_source/ui.split.js',
                    basePath + 'cat_source/ui.opensegment.js',
                    basePath + 'cat_source/sse.js',
                    basePath + 'cat_source/db.js',
                    basePath + 'cat_source/mbc.main.js',
                    basePath + 'cat_source/mbc.templates.js',
                    //WARNING: lxq.main.js: this should always be below qa_check_glossary and
                    //qa_check_blacklist, in order for its event handlers to be excecuted last
                    basePath + 'cat_source/lxq.main.js',
                    basePath + 'cat_source/lxq.templates.js',
                    basePath + 'cat_source/project_completion.*.js',
                    basePath + 'cat_source/segment_notes.*.js',

                    basePath + 'cat_source/ui.review.js',
                    basePath + 'cat_source/review_extended_footer.js',
                    basePath + 'cat_source/review_extended_footer.*.js',
                    basePath + 'cat_source/review_extended.js',
                    basePath + 'cat_source/review_extended.*.js',
                    basePath + 'cat_source/review_improved.js',
                    basePath + 'cat_source/review_improved.*.js',
                    basePath + 'cat_source/review.*.js',

                    basePath + 'cat_source/segment_filter.js',
                    basePath + 'cat_source/segment_filter.*.js',

                    // basePath + 'cat_source/handlebars-helpers.js',

                    basePath + 'cat_source/speech2text.js',
                    basePath + 'tm.js',
                    basePath + 'advancedOptionsTab.js',
                ],
                dest: buildPath + 'app.js'
            },

            libs: {
                src: [
                    basePath + 'lib/jquery-3.3.1.min.js',
                    basePath + 'lib/jquery-ui.min.js',
                    basePath + 'lib/jquery.hotkeys.min.js',
                    basePath + 'lib/js.cookie.js',
                    basePath + 'lib/jquery.powertip.min.js',
                    basePath + 'lib/jquery-dateFormat.min.js',
                    basePath + 'lib/handlebars.runtime-v4.0.5.js',
                    basePath + 'lib/jquery.waypoints.min.js',
                    basePath + 'lib/diff_match_patch.js',
                    basePath + 'lib/rangy-core.js',
                    basePath + 'lib/rangy-selectionsaverestore.js',
                    basePath + 'lib/base64.min.js',
                    basePath + 'lib/moment.min.js',
                    basePath + 'lib/lokijs.min.js',
                    basePath + 'lib/lodash.min.js',
                    basePath + 'lib/sprintf.min.js',
                    basePath + 'lib/calendar.min.js',
                    basePath + 'lib/imagesloaded.min.js',
                    gruntDir + 'semantic/dist/semantic.min.js'
                ],
                dest: buildPath + 'libs.js'
            },

            libs_upload: {
                src: [
                    basePath + 'lib/jquery-3.3.1.min.js',
                    basePath + 'lib/jquery-ui.min.js',
                    basePath + 'lib/lodash.min.js',
                    basePath + 'lib/sprintf.min.js',
                    basePath + 'lib/js.cookie.js',
                    basePath + 'lib/jquery.powertip.min.js',
                    // <!-- The Templates plugin is included to render the upload/download listings -->
                    basePath + 'lib/fileupload/tmpl.min.js',
                    // <!-- The Load Image plugin is included for the preview images and image resizing functionality -->
                    basePath + 'lib/fileupload/load-image.min.js',
                    // <!-- The Canvas to Blob plugin is included for image resizing functionality -->
                    basePath + 'lib/fileupload/canvas-to-blob.min.js',
                    <!-- jQuery Image Gallery -->
                    basePath + 'lib/fileupload/jquery.image-gallery.min.js',
                    <!-- The Iframe Transport is required for browsers without support for XHR file uploads -->
                    basePath + 'lib/fileupload/jquery.iframe-transport.js',
                    <!-- The basic File Upload plugin -->
                    basePath + 'lib/fileupload/jquery.fileupload.js',
                    <!-- The File Upload file processing plugin -->
                    basePath + 'lib/fileupload/jquery.fileupload-fp.js',
                    <!-- The File Upload user interface plugin -->
                    basePath + 'lib/fileupload/jquery.fileupload-ui.js',
                    <!-- The File Upload jQuery UI plugin -->
                    basePath + 'lib/fileupload/jquery.fileupload-jui.js',
                    <!-- The localization script -->
                    basePath + 'lib/fileupload/locale.js',
                    <!-- The main application script -->
                    basePath + 'lib/fileupload/main.js',
                    gruntDir + 'semantic/dist/semantic.min.js'
                ],
                dest: buildPath + 'libs_upload.js'
            },

            semantic: {
                src: [
                    gruntDir + 'semantic/dist/semantic.min.js'
                ],
                dest: buildPath + 'semantic.js'
            },

            common: {
                src: [
                    basePath + 'common.js',
                    basePath + 'user_store.js',
                    basePath + 'login.js'
                ],
                dest: buildPath + 'common.js'
            },
            manage: {
                src: [
                    basePath + 'manage.js',
                    basePath + 'outsource.js',
                    basePath + 'cat_source/es6/react/ajax_utils/*.js'
                ],
                dest: buildPath + 'manage.js'
            },
            analyze: {
                src: [
                    basePath + 'analyze_old.js',
                    basePath + 'forcedelivery.js',
                    basePath + 'outsource_old.js',
                    basePath + 'cat_source/es6/react/ajax_utils/*.js'
                ],
                dest: buildPath + 'analyze_old.js'
            },
            analyze_new: {
                src: [
                    basePath + 'analyze.js',
                    basePath + 'outsource.js',
                    basePath + 'cat_source/es6/react/ajax_utils/*.js'
                ],
                dest: buildPath + 'analyze-build.js'
            },
            upload: {
                src: [
                    basePath + 'gdrive.upload.js',
                    basePath + 'gdrive.picker.js',
                    basePath + 'upload.js',
                    basePath + 'new-project.js',
                    basePath + 'tm.js',
                    basePath + 'cat_source/es6/react/ajax_utils/*.js'
                ],
                dest: buildPath + 'upload.js'
            }

        },

        watch: {
            react_libs: {
                files: [
                    basePath + 'cat_source/es6/react-libs.js'
                ],
                tasks: ['browserify:libs'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            },
            react: {
                files: [
                    basePath + 'cat_source/es6/react/**/*.js'
                ],
                tasks: ['browserify:components'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            },
            js: {
                files: [
                    basePath + 'cat_source/templates/**/*.hbs',
                    basePath + 'cat_source/*.js',
                    basePath + 'tm.js',
                    basePath + 'login.js',
                    basePath + 'advancedOptionsTab.js'
                ],
                tasks: ['concat:js'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            },
            cssCattol: {
                files: cssWatchFiles ,
                tasks: ['sass:distCattol'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            },
            cssUpload: {
                files:  cssWatchFilesUploadPage,
                tasks: ['sass:distUpload'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            },
            cssAnalyze: {
                files:  cssWatchManage,
                tasks: ['sass:distAnalyze', 'replace'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            }
        },
        sass: {
            distCommon: {
                options : {
                    sourceMap : false,
                    includePaths: [ cssBase, cssBase + 'libs/' ]
                },
                src: [
                    cssBase + 'sass/common-main.scss'
                ],
                dest: cssBase + 'build/common.css'
            },
            distCattol: {
                options : {
                    sourceMap : false,
                    includePaths: [ cssBase, cssBase + 'libs/' ]
                },
                src: [
                    cssBase + 'sass/main.scss'
                ],
                dest: cssBase + 'build/app.css'
            },
            distUpload: {
                options : {
                    sourceMap : false,
                    includePaths: [ cssBase, cssBase + 'libs/' ]
                },
                src: [
                    cssBase + 'sass/upload-main.scss'
                ],
                dest: cssBase + 'build/upload-build.css'
            },
            distManage: {
                options : {
                    sourceMap : false,
                    includePaths: [ cssBase, cssBase + 'libs/' ]
                },
                src: [
                    cssBase + 'sass/manage_main.scss'
                ],
                dest: cssBase + 'build/manage-build.css'
            },
            distAnalyze: {
                options : {
                    sourceMap : false,
                    includePaths: [ cssBase, cssBase + 'libs/' ]
                },
                src: [
                    cssBase + 'sass/analyze_main.scss'
                ],
                dest: cssBase + 'build/analyze-build.css'
            },
            distIcons: {
                options : {
                    sourceMap : false,
                    includePaths: [ cssBase ]
                },
                src: [
                    cssBase + 'sass/commons/icons_main.scss'
                ],
                dest: cssBase + 'build/icons.css'
            },
            distSemantic: {
                options : {
                    sourceMap : false,
                    includePaths: [ cssBase, gruntDir + 'semantic/dist/' ]
                },
                src: [
                    cssBase + 'sass/vendor_mc/semantic/matecat_semantic.scss'
                ],
                dest: cssBase + 'build/semantic.css'
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
            },
            css: {
                src: [
                    cssBase + 'build/*'
                ],
                dest: cssBase + 'build/',
                replacements: [
                    {
                        from: 'url(../img',
                        to: 'url(../../img'
                    },
                    {
                        from: '"../../fonts/',
                        to: '"../fonts/'
                    },
                    {
                        from: '"fonts/',
                        to: '"../fonts/'
                    }
                ]
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

    /**
     * bundle:js
     *
     * This task includes all the tasks required to build a final
     * javascript. This is not done in development usually since it
     * would recompile parts that are heavy and not frequently changed
     * like libraries.
     */
    grunt.registerTask('bundle:js', [
        'handlebars',
        'browserify:libs',
        'browserify:components',
        'browserify:qaReportsVersions',
        'concat:libs',
        'concat:libs_upload',
        'concat:semantic',
        'concat:app',
        'concat:common',
        'concat:manage',
        'concat:analyze',
        'concat:analyze_new',
        'concat:upload',
        'replace:version'
    ]);

    /**
     * development:js
     *
     * This task includes compilation of frequently changed develpment files.
     * This includes handlebars templates, react modules via browserify, and
     * reconcats other javascript files.
     * Concat also build the sourceMap. For further reload speed try to turn
     * off sourceMap.
     */
    grunt.registerTask('development:js', [
        'browserify:components',
        'concat:js',
    ]);

    /**
     * Concat js
     * This task is specific for those javascript files which need to be
     * concatenated by grunt. This avoid reworking the react components
     * when it's not needed.
     */
    grunt.registerTask('concat:js', [
        'handlebars',
        'concat:app',
        'concat:common',
        'replace:version'
    ]);

    /**
     * development
     *
     * Development task rebuilds all javascript bundle, which is heavy and
     * should be used only when development starts or when libraries may have
     * changed.
     * Once this is done, it would be better to rely on `watch` task, to reload
     * just development bundles.
     */
    grunt.registerTask('development', [
        'bundle:js',
        'sass',
        'replace:css'
    ]);


    grunt.registerTask('deploy', [
        'bundle:js',
        // 'strip',
        'sass',
        'replace:css'
    ]);

    grunt.registerTask('only-react', [
        'browserify:components',
    ]);

    grunt.registerTask('test', [
        'browserify:test'
    ]);
};
