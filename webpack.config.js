const {globSync} = require('glob')
const matecatConfig = require('./webpack.config_matecat')
const fs = require('fs')
const fsExtra = require('fs-extra')
const path = require('path')

function getDirectories(path) {
  return fs.readdirSync(path).filter(function (file) {
    return fs.statSync(path + '/' + file).isDirectory()
  })
}

//Empty build directories
const pluginDirectories = getDirectories('./plugins/')
pluginDirectories.forEach((dir) => {
  const directory = './plugins/' + dir + '/static/build'
  fsExtra.emptyDirSync(directory)
})

//look for webpack config files
const files = globSync('./plugins/*/webpack.config.js', {
  ignore: './plugins/**/node_modules/**',
})
console.log('Plugins file', files)
let pluginsExports = []
files.forEach((file) => {
  const r = require('./' + file)
  pluginsExports.push(r)
})

module.exports = [matecatConfig, ...pluginsExports]
