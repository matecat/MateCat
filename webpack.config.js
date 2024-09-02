const path = require('path')
const webpack = require('webpack')
const {globSync} = require('glob')
const terser = require('terser')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const HtmlWebPackPlugin = require('html-webpack-plugin')
const WebpackConcatPlugin = require('webpack-concat-files-plugin')
const {sentryWebpackPlugin} = require('@sentry/webpack-plugin')
const https = require('https')
const fs = require('fs')
const ini = require('ini')

const buildPath = './public/build/'
const lxqDownload = './public/buildResources/'

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

function getDirectories(path) {
  return fs.readdirSync(path).filter(function (file) {
    return fs.statSync(path + '/' + file).isDirectory()
  })
}

const matecatConfig = async ({env}, {mode}) => {
  const isDev = mode === 'development'
  const config = ini.parse(fs.readFileSync('./inc/config.ini', 'utf-8'))
  const lxqLicence = config[config.ENV]?.LXQ_LICENSE
  if (lxqLicence) {
    const lxqServer = config[config.ENV].LXQ_SERVER
    if (!fs.existsSync(lxqDownload)) {
      fs.mkdirSync(lxqDownload)
    }
    await downloadFile(
      lxqServer + '/js/lxqlicense.js',
      lxqDownload + 'lxqlicense.js',
    )
  } else {
    if (!fs.existsSync(lxqDownload)) {
      fs.mkdirSync(lxqDownload)
    }
    fs.closeSync(fs.openSync(lxqDownload + 'lxqlicense.js', 'w'))
  }
  let pluginsCattoolFiles = []
  let pluginsUploadFiles = []
  let pluginsAllPagesFiles = []
  const pluginDirectories = getDirectories('./plugins/')
  pluginDirectories.forEach((dir) => {
    const cattoolDirectory = './plugins/' + dir + '/static/src/cattool/'
    pluginsCattoolFiles = pluginsCattoolFiles.concat(
      globSync('./' + cattoolDirectory + '*.js').map((item) => {
        return path.resolve(__dirname, item)
      }),
    )

    const uploadDirectory = './plugins/' + dir + '/static/src/upload/'
    pluginsUploadFiles = pluginsUploadFiles.concat(
      globSync('./' + uploadDirectory + '*.js').map((item) => {
        return path.resolve(__dirname, item)
      }),
    )

    const allDirectory = './plugins/' + dir + '/static/src/all/'
    pluginsAllPagesFiles = pluginsAllPagesFiles.concat(
      globSync('./' + allDirectory + '*.js').map((item) => {
        return path.resolve(__dirname, item)
      }),
    )
  })
  let entryPoints = {}
  if (pluginsCattoolFiles.length > 0) {
    entryPoints.cattoolPlugins = pluginsCattoolFiles
  }
  if (pluginsUploadFiles.length > 0) {
    entryPoints.uploadPlugins = pluginsUploadFiles
  }
  if (pluginsAllPagesFiles.length > 0) {
    entryPoints.allPagesPlugins = pluginsAllPagesFiles
  }
  //look for webpack config files
  const files = globSync('./plugins/*/plugin.webpack.config.js', {
    ignore: './plugins/**/node_modules/**',
  })
  console.log('Plugins file', files)

  let pluginConfig = {}
  files.forEach((file) => {
    const data = fs.readFileSync(file)
    const config = eval(data.toString('utf8'))
    pluginConfig = {...pluginConfig, ...pluginWebpackConfig}
  })
  if (pluginConfig.sentryWebpackPlugin) {
    pluginConfig.sentryWebpackPlugin.release = JSON.stringify(
      config.BUILD_NUMBER,
    )
  }
  return {
    target: 'web',
    watchOptions: {
      aggregateTimeout: 500,
      ignored: ['/node_modules/'],
      poll: 500,
    },
    output: {
      filename: isDev ? '[name].[fullhash].js' : '[name].[contenthash].js',
      sourceMapFilename: isDev ? '[name].[fullhash].js.map' : '[file].map',
      path: path.resolve(__dirname, 'public/build'),
      chunkFilename: isDev
        ? '[name].[fullhash].chunk.js'
        : '[name].[contenthash].chunk.js',
      publicPath: '/public/build/',
      clean: true,
    },
    optimization: {
      moduleIds: 'deterministic',
      runtimeChunk: 'single',
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          include: [
            path.resolve(__dirname, 'public/js'),
            path.resolve(__dirname, 'public/img/icons'),
            path.resolve(__dirname, 'plugins'),
          ],
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
          include: [
            path.resolve(__dirname, 'public/css'),
            path.resolve(__dirname, 'node_modules'),
          ],
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
          include: [
            path.resolve(__dirname, 'public/css/sass'),
            path.resolve(__dirname, 'plugins'),
          ],
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
          include: [path.resolve(__dirname, 'public/img')],
          type: 'asset/resource',
          generator: {
            filename: 'images/[name][ext]',
          },
        },
        {
          test: /\.(woff(2)?|ttf|eot|otf)(\?v=\d+\.\d+\.\d+)?$/,
          include: [path.resolve(__dirname, 'public/css/fonts')],
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
        path.resolve(__dirname, 'public/js/gdrive.upload.js'),
        path.resolve(__dirname, 'public/js/gdrive.picker.js'),
        path.resolve(__dirname, 'public/js/new-project.js'),
        path.resolve(__dirname, 'public/css/sass/upload-main.scss'),
      ],
      ...entryPoints,
      cattool: [
        path.resolve(__dirname, lxqDownload + 'lxqlicense.js'),
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.core.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.init.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.segment.js'),
        path.resolve(__dirname, 'public/js/cat_source/ui.headerTooltips.js'),
        path.resolve(__dirname, 'public/css/sass/main.scss'),
      ],
      dashboard: [
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(
          __dirname,
          'public/js/cat_source/es6/components/projects/Dashboard.js',
        ),
        path.resolve(__dirname, 'public/css/sass/manage_main.scss'),
      ],
      analyze: [
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
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
      new webpack.DefinePlugin({
        'process.env._ENV': JSON.stringify(config.ENV),
        'process.env.version': JSON.stringify(config.BUILD_NUMBER),
        'process.env.MODE': JSON.stringify(mode),
      }),
      !isDev &&
        pluginConfig.sentryWebpackPlugin &&
        sentryWebpackPlugin(pluginConfig.sentryWebpackPlugin),
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
              './public/js/lib/jquery-dateFormat.min.js',
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
        template: path.resolve(
          __dirname,
          './lib/View/templates/_revise_summary.html',
        ),
        chunks: ['qa-report', 'allPagesPlugins'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/upload.html'),
        template: path.resolve(__dirname, './lib/View/templates/_upload.html'),
        chunks: ['upload', 'uploadPlugins', 'allPagesPlugins'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/index.html'),
        template: path.resolve(__dirname, './lib/View/templates/_index.html'),
        chunks: ['cattool', 'cattoolPlugins', 'allPagesPlugins'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/manage.html'),
        template: path.resolve(__dirname, './lib/View/templates/_manage.html'),
        chunks: ['dashboard', 'allPagesPlugins'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/analyze.html'),
        template: path.resolve(__dirname, './lib/View/templates/_analyze.html'),
        chunks: ['analyze', 'allPagesPlugins'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/jobAnalysis.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_jobAnalysis.html',
        ),
        chunks: ['analyze', 'allPagesPlugins'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/xliffToTarget.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_xliffToTarget.html',
        ),
        chunks: ['xliffToTarget', 'allPagesPlugins'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/APIDoc.php'),
        template: path.resolve(__dirname, './lib/View/templates/_APIDoc.php'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/activity_log.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_activity_log.html',
        ),
        chunks: ['cattool'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/job_archived.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_job_archived.html',
        ),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/job_cancelled.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_job_cancelled.html',
        ),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/job_not_found.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_job_not_found.html',
        ),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/project_not_found.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_project_not_found.html',
        ),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/badConfiguration.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_badConfiguration.html',
        ),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/badConfiguration.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_badConfiguration.html',
        ),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/configMissing.html'),
        template: path.resolve(
          __dirname,
          './lib/View/templates/_configMissing.html',
        ),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/offline.html'),
        template: path.resolve(__dirname, './lib/View/templates/_offline.html'),
        chunks: ['commonCss'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/400.html'),
        template: path.resolve(__dirname, './lib/View/templates/_400.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/401.html'),
        template: path.resolve(__dirname, './lib/View/templates/_401.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/403.html'),
        template: path.resolve(__dirname, './lib/View/templates/_403.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/404.html'),
        template: path.resolve(__dirname, './lib/View/templates/_404.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/409.html'),
        template: path.resolve(__dirname, './lib/View/templates/_409.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/500.html'),
        template: path.resolve(__dirname, './lib/View/templates/_500.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/503.html'),
        template: path.resolve(__dirname, './lib/View/templates/_503.html'),
        chunks: ['errorPage'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
    ],
    devtool: isDev ? 'inline-source-map' : 'source-map',
  }
}
module.exports = matecatConfig
