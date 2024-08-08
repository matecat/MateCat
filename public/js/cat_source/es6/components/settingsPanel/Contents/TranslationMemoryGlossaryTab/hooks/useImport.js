import {useEffect, useState, useRef} from 'react'
import PropTypes from 'prop-types'
import ModalsActions from '../../../../../actions/ModalsActions'
import AlertModal from '../../../../modals/AlertModal'
import {loadTMX} from '../../../../../api/loadTMX'
import {loadGlossaryFile} from '../../../../../api/loadGlossaryFile'
import {uploadGlossary} from '../../../../../api/uploadGlossary/uploadGlossary'
import {uploadTm} from '../../../../../api/uploadTm'
import {checkGlossaryImport} from '../../../../../api/checkGlossaryImport'
import ConfirmMessageModal from '../../../../modals/ConfirmMessageModal'
import CatToolActions from '../../../../../actions/CatToolActions'

export const IMPORT_TYPE = {
  tmx: 'tmx',
  glossary: 'glossary',
}

const DELAY_GET_STATUS = 1000

function useImport({type, row, onClose}) {
  const [files, setFiles] = useState([])
  const [uuids, setUuids] = useState(undefined)
  const [status, setStatus] = useState([])

  const onCloseRef = useRef()
  onCloseRef.current = onClose

  const {key} = row

  useEffect(() => {
    if (!uuids?.length) return

    let tmOut

    const getStatus = () => {
      uuids.forEach(({uuid, name}) => {
        const promise =
          type === IMPORT_TYPE.tmx
            ? loadTMX({uuid, key, name})
            : loadGlossaryFile({id: uuid})

        promise
          .then(({data}) => {
            const dataFiles = Array.isArray(data) ? data : [data]
            const statusFiles = dataFiles.map(
              ({uuid, status, completed, totals}) => ({
                uuid,
                isCompleted: status === 1,
                percentage: totals ? (completed / totals) * 100 : 0,
              }),
            )
            setStatus(statusFiles)

            const isCompletedAll = statusFiles.every(
              ({isCompleted}) => isCompleted,
            )

            if (!isCompletedAll) {
              tmOut = setTimeout(getStatus, DELAY_GET_STATUS)
            } else {
              tmOut = setTimeout(onCloseRef.current, 2000)
            }
          })
          .catch((error) => {
            // TODO implementare errori upload files multipli
            setStatus(uuids.map((item) => ({...item, errors: error.errors})))
            const message =
              error.errors && error.errors.length > 0
                ? error.errors[0].message
                : 'Error while importing your file'
            CatToolActions.addNotification({
              title: 'Error import',
              type: 'error',
              text: message,
              position: 'br',
              allowHtml: true,
              timer: 5000,
            })
          })
      })
    }

    getStatus()

    return () => clearTimeout(tmOut)
  }, [uuids, key, type])

  const onChangeFiles = (e) => {
    setStatus([])
    if (e.target.files && e.target.files.length > 0) {
      if (
        e.target.files[0].size > config.maxTMXFileSize &&
        type === IMPORT_TYPE.tmx
      ) {
        onErrorFileTooBig(config.maxTMXFileSize / (1024 * 1024))
      } else {
        setFiles(Array.from(e.target.files))
      }
    }
  }

  const uploadGlossaryFn = () => {
    const upload = () => {
      uploadGlossary({filesToUpload: files, tmKey: row.key, keyName: row.name})
        .then(({data}) =>
          setUuids(
            data.uuids.map((item) => ({
              ...item,
              filename: files[0].name,
            })),
          ),
        )
        .catch((errors) => onErrorUpload(errors))
    }
    checkGlossaryImport({
      filesToUpload: files,
      tmKey: row.key,
      keyName: row.name,
    })
      .then((response) => {
        if (
          response.results?.length &&
          response.results[0].numberOfLanguages > 10
        ) {
          //show Modal
          ModalsActions.showModalComponent(ConfirmMessageModal, {
            cancelText: 'Cancel',
            successCallback: () => upload(),
            successText: 'Upload glossary',
            text:
              'You are uploading a glossary file containing more than 10 locale columns.<br/> Please note that locale ' +
              'permutations are turned off for glossaries containing more than 10 locales.<br/><br/>You can find additional ' +
              "details on permutations inside <a href='https://guides.matecat.com/glossary-file-format' target='_blank'>Matecat's user guide</a>.",
          })
        } else {
          upload()
        }
      })
      .catch((errors) => onErrorUpload(errors))
  }

  const uploadTmxFn = () => {
    uploadTm({filesToUpload: files, tmKey: row.key, keyName: row.name})
      .then(({data}) =>
        setUuids(
          data.uuids.map((item) => ({
            ...item,
            filename: item.name,
          })),
        ),
      )
      .catch((errors) => onErrorUpload(errors))
  }

  const onSubmit = (event) => {
    if (type === IMPORT_TYPE.tmx) {
      uploadTmxFn()
    } else {
      uploadGlossaryFn()
    }
    setUuids([])
    event.preventDefault()
  }

  const onReset = () => {
    setFiles([])
    setUuids(undefined)
    setStatus([])

    if (!uuids?.length) onCloseRef.current()
  }

  const onErrorUpload = (error) => {
    CatToolActions.addNotification({
      title: 'Error upload',
      type: 'error',
      text: error.errors?.[0].message ?? 'Error',
      position: 'br',
      allowHtml: true,
      timer: 5000,
    })
    setStatus([{errors: error.errors}])
    setUuids(undefined)
  }

  const onErrorFileTooBig = (mb) => {
    ModalsActions.showModalComponent(
      AlertModal,
      {
        text: 'File is too big.<br/>The maximuxm size allowed is ' + mb + 'MB.',
        buttonText: 'OK',
      },
      'File too big',
    )
  }

  return {files, uuids, status, onChangeFiles, onSubmit, onReset}
}

useImport.propTypes = {
  type: PropTypes.oneOf(Object.values(IMPORT_TYPE)).isRequired,
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}

export default useImport
