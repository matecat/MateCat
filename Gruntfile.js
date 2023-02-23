const sass = require('node-sass')

const {getBabelPresets} = require('./babel.utils')

const basePath = 'public/js/'
const buildPath = 'public/js/build/'
const incPath = 'inc/'
const cssBase = 'public/css/'

const cssWatchFiles = [
  cssBase + 'sass/variables.scss',
  cssBase + 'common.css',
  cssBase + 'mbc-style.css',
  cssBase + 'segment-notes.css',
  cssBase + 'project-completion-feature.css',
  cssBase + 'editlog.css',
  cssBase + 'jquery.powertip.min.css',
  cssBase + 'lexiqa.css',
  cssBase + 'sass/*.scss',
  cssBase + 'sass/commons/*.scss',
  cssBase + 'sass/components/*/*.scss',
  cssBase + 'sass/modals/*',
  cssBase + 'sass/vendor_mc/*',
]
const cssWatchFilesUploadPage = [
  cssBase + 'sass/variables.scss',
  cssBase + 'common.css',
  cssBase + 'upload-page.scss',
  cssBase + 'popup.css',
  cssBase + 'sass/modals/*',
  cssBase + 'sass/notifications.scss',
]
const cssWatchManage = [cssBase + 'sass/commons/*']

const babelifyTransform = ['babelify', getBabelPresets('browser')]

module.exports = function (grunt) {
  const conf = grunt.file.read(incPath + 'version.ini')
  const version = conf
    .match(/version[ ]+=[ ]+.*/gi)[0]
    .replace(/version[ ]+=[ ]+(.*?)/gi, '$1')
  grunt.log.ok('Matecat Version: ' + version)

  //Lexiqa
  const conf2 = grunt.file.read(incPath + 'config.ini')
  const lxqLicense = conf2.match(/^LXQ_LICENSE[ ]+=[ ]+.*/gim)
  let lxqServer
  if (lxqLicense) {
    const lxqServerMatch = conf2.match(/^LXQ_SERVER[ ]+=[ ]+.*/gim)
    lxqServer = lxqServerMatch
      ? lxqServerMatch[0]
          .replace(/LXQ_SERVER[ ]+=[ ]+(.*?)/gi, '$1')
          .replace(/"/g, '')
      : undefined
    grunt.log.ok('Lexiqa Server: ' + lxqServer)
  }

  grunt.initConfig({
    /**
     * Browserify
     *
     * This is the new build process based on browserify
     * to take advantage of transforms, plugins and import
     * and require syntax to declare dependencies.
     * Use this for current / future development.
     *
     * All imports to be attached to window should be defined in
     * the entry point js file.
     */
    curl: {
      'public/js/build/lxqlicense.js': lxqServer + '/js/lxqlicense.js',
    },
    browserify: {
      qualityReport: {
        options: {
          transform: [babelifyTransform],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
          watch: true,
        },
        src: [
          basePath + 'cat_source/es6/react-libs.js',
          basePath + 'cat_source/es6/components.js',
          basePath + 'common.js',
          basePath + 'user_store.js',
          basePath + 'login.js',
          basePath +
            'cat_source/es6/components/quality_report/QualityReport.js',
        ],
        dest: buildPath + 'qa-report.js',
      },
      manage: {
        options: {
          transform: [babelifyTransform],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
          watch: true,
        },
        src: [
          basePath + 'cat_source/es6/react-libs.js',
          basePath + 'cat_source/es6/components.js',
          basePath + 'common.js',
          basePath + 'user_store.js',
          basePath + 'login.js',
          basePath + 'cat_source/es6/components/projects/Dashboard.js',
          basePath + 'outsource.js',
        ],
        dest: buildPath + 'manage.js',
      },
      app: {
        options: {
          transform: [babelifyTransform],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
          watch: true,
        },
        src: [
          basePath + 'cat_source/es6/react-libs.js',
          basePath + 'cat_source/es6/components.js',
          basePath + 'common.js',
          basePath + 'user_store.js',
          basePath + 'login.js',
          basePath + 'build/lxqlicense.js',
          basePath + 'cat_source/ui.core.js',
          basePath + 'cat_source/ui.segment.js',
          basePath + 'cat_source/ui.init.js',
          basePath + 'cat_source/ui.events.js',
          basePath + 'cat_source/ui.headerTooltips.js',
          basePath + 'cat_source/ui.review.js',
          basePath + 'cat_source/review/review_simple.js',
          basePath + 'cat_source/review_extended/review_extended.default.js',
          basePath +
            'cat_source/review_extended/review_extended.ui_extension.js',
          basePath +
            'cat_source/review_extended/review_extended.common_events.js',
          basePath + 'cat_source/segment_filter.common_extension.js',
          basePath + 'cat_source/speech2text.js',
          basePath + 'tm.js',
          basePath + 'advancedOptionsTab.js',
        ],
        dest: `${buildPath}app.js`,
      },
      analyze: {
        options: {
          transform: [babelifyTransform],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
          watch: true,
        },
        src: [
          basePath + 'cat_source/es6/react-libs.js',
          basePath + 'cat_source/es6/components.js',
          basePath + 'common.js',
          basePath + 'user_store.js',
          basePath + 'login.js',
          basePath + 'cat_source/es6/pages/AnalyzePage.js',
        ],
        dest: buildPath + 'analyze-build.js',
      },
      upload: {
        options: {
          transform: [babelifyTransform],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
          watch: true,
        },
        src: [
          basePath + 'lib/fileupload/main.js',
          basePath + 'cat_source/es6/react-libs.js',
          basePath + 'cat_source/es6/components.js',
          basePath + 'common.js',
          basePath + 'user_store.js',
          basePath + 'login.js',
          basePath + 'gdrive.upload.js',
          basePath + 'gdrive.picker.js',
          basePath + 'new-project.js',
          basePath + 'tm.js',
        ],
        dest: buildPath + 'upload.js',
      },
      xliffToTarget: {
        options: {
          transform: [babelifyTransform],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
          watch: true,
        },
        src: [
          basePath + 'lib/fileupload/main.js',
          basePath + 'cat_source/es6/react-libs.js',
          basePath + 'cat_source/es6/components.js',
          basePath + 'common.js',
          basePath + 'user_store.js',
          basePath + 'login.js',
        ],
        dest: buildPath + 'xliffToTarget.js',
      },
      pee: {
        options: {
          transform: [babelifyTransform],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
          watch: true,
        },
        src: [
          basePath + 'lib/jquery.tablesorter.js',
          basePath + 'lib/jquery.tablesorter.widgets.js',
          basePath + 'lib/semantic.min.js',
          basePath + 'pee.js',
        ],
        dest: buildPath + 'pee.js',
      },
      components: {
        options: {
          transform: [babelifyTransform],
          browserifyOptions: {
            paths: [__dirname + '/node_modules'],
          },
          watch: true,
        },
        src: [basePath + 'cat_source/es6/components.js'],
        dest: buildPath + 'components.js',
      },
    },

    /**
     * Concat
     *
     * This is pure concatenation of files, deprecated in favour of
     * browserify. Everything here should be migrated sooner or later.
     *
     * This concat makes use of a file generated by the `browserify`
     * step, where we process es6 code, react, and import libraries.
     */
    concat: {
      libs: {
        src: [
          basePath + 'lib/jquery-3.3.1.min.js',
          basePath + 'lib/jquery-ui.min.js',
          basePath + 'lib/jquery.hotkeys.min.js',
          basePath + 'lib/jquery.powertip.min.js',
          basePath + 'lib/jquery-dateFormat.min.js',
          basePath + 'lib/diff_match_patch.js',
          basePath + 'lib/calendar.min.js',
          basePath + 'lib/jquery.atwho.min.js',
          basePath + 'lib/jquery.caret.min.js',
          basePath + 'lib/semantic.min.js',
        ],
        dest: buildPath + 'libs.js',
      },

      libs_upload: {
        src: [
          basePath + 'lib/jquery-3.3.1.min.js',
          basePath + 'lib/jquery-ui.min.js',
          basePath + 'lib/diff_match_patch.js',
          basePath + 'lib/jquery.powertip.min.js',

          // The Templates plugin is included to render the upload/download listings
          basePath + 'lib/fileupload/tmpl.min.js',

          // The Load Image plugin is included for the preview images and image resizing functionality
          basePath + 'lib/fileupload/load-image.min.js',

          // The Canvas to Blob plugin is included for image resizing functionality
          basePath + 'lib/fileupload/canvas-to-blob.min.js',

          // jQuery Image Gallery
          basePath + 'lib/fileupload/jquery.image-gallery.min.js',

          // The Iframe Transport is required for browsers without support for XHR file uploads
          basePath + 'lib/fileupload/jquery.iframe-transport.js',

          // The basic File Upload plugin
          basePath + 'lib/fileupload/jquery.fileupload.js',

          // The File Upload file processing plugin
          basePath + 'lib/fileupload/jquery.fileupload-fp.js',

          // The File Upload user interface plugin
          basePath + 'lib/fileupload/jquery.fileupload-ui.js',

          // The File Upload jQuery UI plugin
          basePath + 'lib/fileupload/jquery.fileupload-jui.js',

          // The localization script
          basePath + 'lib/fileupload/locale.js',
          basePath + 'lib/semantic.min.js',
        ],
        dest: buildPath + 'libs_upload.js',
      },
    },

    watch: {
      cssCattol: {
        files: cssWatchFiles,
        tasks: ['sass'],
        options: {
          interrupt: true,
          livereload: true,
        },
      },
      cssUpload: {
        files: cssWatchFilesUploadPage,
        tasks: ['sass:distUpload'],
        options: {
          interrupt: true,
          livereload: true,
        },
      },
      cssAnalyze: {
        files: cssWatchManage,
        tasks: ['sass:distAnalyze', 'replace'],
        options: {
          interrupt: true,
          livereload: true,
        },
      },
    },
    sass: {
      distCommon: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase, cssBase + 'libs/'],
        },
        src: [cssBase + 'sass/common-main.scss'],
        dest: cssBase + 'build/common.css',
      },
      distCattol: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase, cssBase + 'libs/'],
        },
        src: [cssBase + 'sass/main.scss'],
        dest: cssBase + 'build/app.css',
      },
      distUpload: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase, cssBase + 'libs/'],
        },
        src: [cssBase + 'sass/upload-main.scss'],
        dest: cssBase + 'build/upload-build.css',
      },
      distManage: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase, cssBase + 'libs/'],
        },
        src: [cssBase + 'sass/manage_main.scss'],
        dest: cssBase + 'build/manage-build.css',
      },
      distAnalyze: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase, cssBase + 'libs/'],
        },
        src: [cssBase + 'sass/analyze_main.scss'],
        dest: cssBase + 'build/analyze-build.css',
      },
      distQR: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase, cssBase + 'libs/'],
        },
        src: [cssBase + 'sass/quality-report.scss'],
        dest: cssBase + 'build/quality_report.css',
      },
      distIcons: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase],
        },
        src: [cssBase + 'sass/commons/icons_main.scss'],
        dest: cssBase + 'build/icons.css',
      },
      distSemantic: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase],
        },
        src: [cssBase + 'sass/vendor_mc/semantic/matecat_semantic.scss'],
        dest: cssBase + 'build/semantic.css',
      },
      distLegacy: {
        options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [cssBase],
        },
        src: [cssBase + 'sass/legacy-misc.scss'],
        dest: cssBase + 'build/legacy-misc.css',
      },
    },
    strip: {
      app: {
        src: buildPath + 'app.js',
        options: {
          inline: true,
          nodes: ['console.log'],
        },
      },
    },
    replace: {
      version: {
        src: [buildPath + 'app.js'],
        dest: buildPath + 'app.js',
        replacements: [
          {
            from: /this\.version = '(.*?)'/gi,
            to: 'this.version = "' + version + '"',
          },
        ],
      },
      css: {
        src: [cssBase + 'build/*'],
        dest: cssBase + 'build/',
        replacements: [
          {
            from: 'url(../img',
            to: 'url(../../img',
          },
          {
            from: '"../../fonts/',
            to: '"../fonts/',
          },
          {
            from: '"fonts/',
            to: '"../fonts/',
          },
        ],
      },
    },
  })

  grunt.loadNpmTasks('grunt-curl')
  grunt.loadNpmTasks('grunt-contrib-concat')
  grunt.loadNpmTasks('grunt-contrib-watch')
  grunt.loadNpmTasks('grunt-text-replace')
  grunt.loadNpmTasks('grunt-strip')
  grunt.loadNpmTasks('grunt-sass')
  grunt.loadNpmTasks('grunt-browserify')

  /**
   * bundle:js
   *
   * This task includes all the tasks required to build a final
   * javascript. This is not done in development usually since it
   * would recompile parts that are heavy and not frequently changed
   * like libraries.
   */
  grunt.registerTask('bundle:js', [
    'browserify:qualityReport',
    'browserify:manage',
    'browserify:app',
    'browserify:upload',
    'browserify:analyze',
    'browserify:xliffToTarget',
    'browserify:pee',
    'concat:libs',
    'concat:libs_upload',
    'replace:version',
  ])

  /**
   * bundleDev:js
   *
   * This task includes all the tasks required to build a final
   * javascript. This is not done in development usually since it
   * would recompile parts that are heavy and not frequently changed
   * like libraries.
   */
  grunt.registerTask('bundleDev:js', [
    'browserify:qualityReport',
    'browserify:manage',
    'browserify:app',
    'browserify:upload',
    'browserify:analyze',
    'browserify:xliffToTarget',
    'browserify:pee',
    'browserify:components',
    'concat:libs',
    'concat:libs_upload',
    'replace:version',
  ])
  /**
   * development
   *
   * Development task rebuilds all javascript bundle, which is heavy and
   * should be used only when development starts or when libraries may have
   * changed.
   * Once this is done, it would be better to rely on `watch` task, to reload
   * just development bundles.
   */
  grunt.registerTask('development', function () {
    var tasks = ['bundleDev:js', 'sass', 'replace:css']
    if (lxqServer) {
      tasks.unshift('curl')
    }
    grunt.task.run(tasks)
  })

  grunt.registerTask('deploy', function () {
    var tasks = ['bundleDev:js', 'sass', 'replace:css']
    if (lxqServer) {
      tasks.unshift('curl')
    }
    grunt.task.run(tasks)
  })
}
