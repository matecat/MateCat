export const getPrintableFileSize = (filesizeInBytes) => {
  filesizeInBytes = filesizeInBytes / 1024
  let ext = ' KB'
  if (filesizeInBytes > 1024) {
    filesizeInBytes = filesizeInBytes / 1024
    ext = ' MB'
  }
  return Math.round(filesizeInBytes * 100) / 100 + ext
}
