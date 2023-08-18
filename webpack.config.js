const path = require('path')
const terser = require('terser')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const HtmlWebPackPlugin = require('html-webpack-plugin')
const WebpackConcatPlugin = require('webpack-concat-files-plugin')
const https = require('https')
const fs = require('fs')
const ini = require('ini')

const downloadFile = async (url, dest, cb) => {
  const file = fs.createWriteStream(dest)
  return new Promise((resolve, reject) => {
    https.get(url, function (response) {
      response.pipe(file)
      file.on('finish', function () {
        resolve()
      })
      file.on('error', function (response) {
        reject()
      })
    })
  })
}

module.exports = async ({env}, {mode}) => {
  const isDev = mode === 'development'
  const config = ini.parse(fs.readFileSync('./inc/config.ini', 'utf-8'))
  const lxqLicence = config[mode]?.LXQ_LICENSE
  if (lxqLicence) {
    const lxqServer = config[mode].LXQ_SERVER
    if (!fs.existsSync('./public/build')) {
      fs.mkdirSync('./public/build')
    }
    await downloadFile(
      lxqServer + '/js/lxqlicense.js',
      './public/build/lxqlicense.js',
    )
  } else {
    fs.closeSync(fs.openSync('./public/build/lxqlicense.js', 'w'))
  }
  return {
    target: 'web',
    watchOptions: {
      aggregateTimeout: 500,
      ignored: /node_modules/,
      poll: 500,
    },
    output: {
      filename: isDev ? '[name].[fullhash].js' : '[name].[contenthash].js',
      sourceMapFilename: isDev ? '[name].[fullhash].js.map' : '[file].map',
      path: path.resolve(__dirname, 'public/build'),
      chunkFilename: isDev
        ? '[name].[fullhash].chunk.js'
        : '[name].[contenthash].chunk.js',
      publicPath: '/',
    },
    optimization: {
      moduleIds: 'deterministic',
      runtimeChunk: 'single',
      /*splitChunks: {
        cacheGroups: {
          react: {
            test: /[\\/]node_modules[\\/](react|react-dom|react-router-dom)[\\/]/,
            name: 'vendors-react',
            chunks: 'all',
          },
          corejsVendor: {
            test: /[\\/]node_modules[\\/](core-js)[\\/]/,
            name: 'vendor-corejs',
            chunks: 'all',
          },
        },
      },*/
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: '/node_modules/',
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env'],
            },
          },
        },
        {
          test: /\.css$/i,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
              options: {
                publicPath: '/public/build/',
              },
            },
            'css-loader',
          ],
        },
        {
          test: /\.s[ac]ss$/i,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
              options: {
                publicPath: '/public/build/',
              },
            },
            'css-loader',
            'sass-loader',
          ],
        },
        {
          test: /\.(png|jpe?g|gif|mp4|svg|ico)$/i,
          type: 'asset/resource',
          generator: {
            filename: 'images/[name][ext]',
          },
        },
        {
          test: /\.(woff(2)?|ttf|eot|otf)(\?v=\d+\.\d+\.\d+)?$/,
          type: 'asset/resource',
          generator: {
            filename: 'fonts/[name][ext]',
          },
        },
      ],
    },
    entry: {
      'qa-report': [
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/login.js'),
        path.resolve(
          __dirname,
          'public/js/cat_source/es6/components/quality_report/QualityReport.js',
        ),
        path.resolve(__dirname, 'public/css/sass/quality-report.scss'),
      ],
      upload: [
        path.resolve(__dirname, 'public/js/upload_main.js'),
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/login.js'),
        path.resolve(__dirname, 'public/js/gdrive.upload.js'),
        path.resolve(__dirname, 'public/js/gdrive.picker.js'),
        path.resolve(__dirname, 'public/js/new-project.js'),
        path.resolve(__dirname, 'public/css/sass/upload-main.scss'),
      ],
      cattool: [
        path.resolve(__dirname, 'public/build/lxqlicense.js'),
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/login.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.core.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.segment.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.init.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.events.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.headerTooltips.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.review.js'),
        path.resolve(
          __dirname,
          'public/js/cat_source/review_extended/review_extended.default.js',
        ),
        path.resolve(
          __dirname,
          'public/js/cat_source/review_extended/review_extended.ui_extension.js',
        ),
        path.resolve(
          __dirname,
          'public/js/cat_source/review_extended/review_extended.common_events.js',
        ),
        path.resolve(
          __dirname,
          'public/js/cat_source/segment_filter.common_extension.js',
        ),
        path.resolve(__dirname, 'public/css/sass/main.scss'),
      ],
      dashboard: [
        path.resolve(__dirname, 'public/js/cat_source/es6/react-libs.js'),
        path.resolve(__dirname, 'public/js/cat_source/es6/components.js'),
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/login.js'),
        path.resolve(
          __dirname,
          'public/js/cat_source/es6/components/projects/Dashboard.js',
        ),
        path.resolve(__dirname, 'public/css/sass/manage_main.scss'),
      ],
      analyze: [
        path.resolve(__dirname, 'public/js/cat_source/es6/react-libs.js'),
        path.resolve(__dirname, 'public/js/cat_source/es6/components.js'),
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/login.js'),
        path.resolve(
          __dirname,
          'public/js/cat_source/es6/pages/AnalyzePage.js',
        ),
        path.resolve(__dirname, 'public/css/sass/analyze_main.scss'),
      ],
      xliffToTarget: [
        path.resolve(__dirname, 'public/js/upload_main.js'),
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/login.js'),
        path.resolve(__dirname, 'public/js/xliffToTarget.js'),
        path.resolve(__dirname, 'public/css/sass/upload-main.scss'),
        path.resolve(__dirname, 'public/css/sass/main.scss'),
      ],
      commonCss: [
        path.resolve(__dirname, 'public/css/sass/main.scss'),
        path.resolve(__dirname, 'public/css/sass/legacy-misc.scss'),
      ],
      errorPage: [path.resolve(__dirname, 'public/css/sass/upload-main.scss')],
    },
    plugins: [
      new WebpackConcatPlugin({
        bundles: [
          {
            src: [
              './public/js/lib/jquery-3.3.1.min.js',
              './public/js/lib/jquery-ui.min.js',
              './public/js/lib/fileupload/tmpl.min.js',
              './public/js/lib/fileupload/load-image.min.js',
              './public/js/lib/fileupload/canvas-to-blob.min.js',
              './public/js/lib/fileupload/jquery.image-gallery.min.js',
              './public/js/lib/fileupload/jquery.iframe-transport.js',
              './public/js/lib/fileupload/jquery.fileupload.js',
              './public/js/lib/fileupload/jquery.fileupload-fp.js',
              './public/js/lib/fileupload/jquery.fileupload-ui.js',
              './public/js/lib/fileupload/jquery.fileupload-jui.js',
              './public/js/lib/fileupload/locale.js',
              './public/js/lib/semantic.min.js',
            ],
            dest: './public/build/lib_upload.min.js',
            transforms: {
              after: async (code) => {
                const minifiedCode = await terser.minify(code)
                return minifiedCode.code
              },
            },
          },
        ],
      }),
      new WebpackConcatPlugin({
        bundles: [
          {
            src: [
              './public/js/lib/jquery-3.3.1.min.js',
              './public/js/lib/jquery-ui.min.js',
              './public/js/lib/jquery.hotkeys.min.js',
              './public/js/lib/jquery-dateFormat.min.js',
              './public/js/lib/calendar.min.js',
              './public/js/lib/jquery.atwho.min.js',
              './public/js/lib/jquery.caret.min.js',
              './public/js/lib/semantic.min.js',
            ],
            dest: './public/build/libs.js',
            transforms: {
              after: async (code) => {
                const minifiedCode = await terser.minify(code)
                return minifiedCode.code
              },
            },
          },
        ],
      }),
      new MiniCssExtractPlugin({
        filename: '[name].[contenthash].css',
        chunkFilename: '[id].[contenthash].css',
        ignoreOrder: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/revise_summary.html'),
        template: path.resolve(__dirname, './lib/View/_revise_summary.html'),
        chunks: ['qa-report'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/upload.html'),
        template: path.resolve(__dirname, './lib/View/_upload.html'),
        chunks: ['upload'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/index.html'),
        template: path.resolve(__dirname, './lib/View/_index.html'),
        chunks: ['cattool'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/manage.html'),
        template: path.resolve(__dirname, './lib/View/_manage.html'),
        chunks: ['dashboard'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/analyze.html'),
        template: path.resolve(__dirname, './lib/View/_analyze.html'),
        chunks: ['analyze'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/jobAnalysis.html'),
        template: path.resolve(__dirname, './lib/View/_jobAnalysis.html'),
        chunks: ['analyze'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/xliffToTarget.html'),
        template: path.resolve(__dirname, './lib/View/_xliffToTarget.html'),
        chunks: ['xliffToTarget'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/APIDoc.php'),
        template: path.resolve(__dirname, './lib/View/_APIDoc.php'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/activity_log.html'),
        template: path.resolve(__dirname, './lib/View/_activity_log.html'),
        chunks: ['cattool'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/job_archived.html'),
        template: path.resolve(__dirname, './lib/View/_job_archived.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/job_cancelled.html'),
        template: path.resolve(__dirname, './lib/View/_job_cancelled.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/job_not_found.html'),
        template: path.resolve(__dirname, './lib/View/_job_not_found.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/project_not_found.html'),
        template: path.resolve(__dirname, './lib/View/_project_not_found.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/badConfiguration.html'),
        template: path.resolve(__dirname, './lib/View/_badConfiguration.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/badConfiguration.html'),
        template: path.resolve(__dirname, './lib/View/_badConfiguration.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/configMissing.html'),
        template: path.resolve(__dirname, './lib/View/_configMissing.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/offline.html'),
        template: path.resolve(__dirname, './lib/View/_offline.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/400.html'),
        template: path.resolve(__dirname, './lib/View/_400.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/401.html'),
        template: path.resolve(__dirname, './lib/View/_401.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/403.html'),
        template: path.resolve(__dirname, './lib/View/_403.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/404.html'),
        template: path.resolve(__dirname, './lib/View/_404.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/409.html'),
        template: path.resolve(__dirname, './lib/View/_409.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/500.html'),
        template: path.resolve(__dirname, './lib/View/_500.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/503.html'),
        template: path.resolve(__dirname, './lib/View/_503.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
    ],
    devtool: isDev ? 'inline-source-map' : 'source-map',
  }
}
