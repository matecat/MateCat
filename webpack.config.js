const path = require('path')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const HtmlWebPackPlugin = require('html-webpack-plugin')

module.exports = () => {
  const isDev = false
  return {
    target: 'web',
    output: {
      // filename: isDev ? '[name].[fullhash].js' : '[name].[contenthash].js',
      filename: '[name].js',
      // sourceMapFilename: isDev ? '[name].[fullhash].js.map' : '[file].map',
      sourceMapFilename: '[name].js.map',
      path: path.resolve(__dirname, 'public/build'),
      // chunkFilename: isDev
      //   ? '[name].[fullhash].chunk.js'
      //   : '[name].[contenthash].chunk.js',
      chunkFilename: '[name].chunk.js',
      publicPath: '/',
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
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
        path.resolve(__dirname, 'public/js/cat_source/es6/react-libs.js'),
        path.resolve(__dirname, 'public/js/cat_source/es6/components.js'),
        path.resolve(__dirname, 'public/js/common.js'),
        path.resolve(__dirname, 'public/js/user_store.js'),
        path.resolve(__dirname, 'public/js/login.js'),
        path.resolve(__dirname, 'public/css/sass/quality-report.scss'),
        path.resolve(
          __dirname,
          'public/js/cat_source/es6/components/quality_report/QualityReport.js',
        ),
      ],
    },
    plugins: [
      !isDev &&
        new MiniCssExtractPlugin({
          // filename: '[name].[contenthash].css',
          // chunkFilename: '[id].[contenthash].css',
          filename: '[name].css',
          chunkFilename: '[id].css',
          ignoreOrder: true,
        }),
      /*new HtmlWebPackPlugin({
        filename: path.resolve(__dirname, 'lib/View/revise_summary.html'),
        template: './lib/View/revise_summary_template.html',
        chunks: ['qa-report'],
      }),*/
    ],
  }
}
