const path = require('path')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const HtmlWebPackPlugin = require('html-webpack-plugin')

module.exports = ({env}) => {
  const isDev = env === 'development'
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
          exclude: ['/node_modules/', '/public/js/lib/fileupload/'],
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
      libs: [
        path.resolve(__dirname, 'public/js/lib/jquery-3.3.1.min.js'),
        path.resolve(__dirname, 'public/js/lib/jquery-ui.min.js'),
        path.resolve(__dirname, 'public/js/lib/jquery.hotkeys.min.js'),
        path.resolve(__dirname, 'public/js/lib/jquery-dateFormat.min.js'),
        path.resolve(__dirname, 'public/js/lib/calendar.min.js'),
        path.resolve(__dirname, 'public/js/lib/jquery.atwho.min.js'),
        path.resolve(__dirname, 'public/js/lib/jquery.caret.min.js'),
        path.resolve(__dirname, 'public/js/lib/semantic.min.js'),
      ],
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
        path.resolve(__dirname, 'public/js/lib/fileupload/main.js'),
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/login.js'),
        path.resolve(__dirname, 'public/js/gdrive.upload.js'),
        path.resolve(__dirname, 'public/js/gdrive.picker.js'),
        path.resolve(__dirname, 'public/js/new-project.js'),
        path.resolve(__dirname, 'public/css/sass/upload-main.scss'),
      ],
    },
    plugins: [
      !isDev &&
        new MiniCssExtractPlugin({
          filename: '[name].[contenthash].css',
          chunkFilename: '[id].[contenthash].css',
          ignoreOrder: true,
        }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/revise_summary.html'),
        template: path.resolve(__dirname, './lib/View/_revise_summary.html'),
        chunks: ['qa-report', 'libs'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
      new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, './lib/View/upload.html'),
        template: path.resolve(__dirname, './lib/View/_upload.html'),
        chunks: ['libs', 'upload'],
        publicPath: '/public/build/',
        xhtml: true,
      }),
    ],
    devtool: isDev ? 'inline-source-map' : 'source-map',
  }
}
