export const getPrintableFileSize = (filesizeInBytes) => {
  filesizeInBytes = filesizeInBytes / 1024
  let ext = ' KB'
  if (filesizeInBytes > 1024) {
    filesizeInBytes = filesizeInBytes / 1024
    ext = ' MB'
  }
  return Math.round(filesizeInBytes * 100) / 100 + ext
}

export const getFileLimitTooltip = (files) =>
  files.length >= config.maxNumberFiles
    ? `The limit of ${config.maxNumberFiles} files per project has been hit. You cannot add additional files.`
    : undefined
