import {downloadFromUrl} from '../downloadFromUrl'

/**
 * Download the translated files of a job
 *
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param {boolean} [checkErrors=true]
 * @param {function({loaded: number, total: number})} [onProgress] called as the file arrives
 * @param {AbortSignal} [signal] aborts the download
 * @returns {Promise<void>}
 */
export const downloadFile = ({
  idJob = config.id_job,
  password = config.password,
  checkErrors = true,
  onProgress,
  signal,
}) =>
  downloadFromUrl({
    url: `${config.basepath}api/v2/translation/${idJob}/${password}?download_type=all&disableErrorCheck=${checkErrors ? 0 : 1}`,
    onProgress,
    signal,
  })
