import {useContext, useEffect, useState, useRef} from 'react'
import PropTypes from 'prop-types'
import {TranslationMemoryGlossaryTabContext} from '../TranslationMemoryGlossaryTab'
import ModalsActions from '../../../../../actions/ModalsActions'
import AlertModal from '../../../../modals/AlertModal'
import {loadTMX} from '../../../../../api/loadTMX'
import {loadGlossaryFile} from '../../../../../api/loadGlossaryFile'
import {uploadGlossary} from '../../../../../api/uploadGlossary/uploadGlossary'
import {uploadTm} from '../../../../../api/uploadTm'

export const IMPORT_TYPE = {
  tmx: 'tmx',
  glossary: 'glossary',
}

const DELAY_GET_STATUS = 1000

function useImport({type, row, onClose}) {
  const {setNotification} = useContext(TranslationMemoryGlossaryTabContext)

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
            setNotification({
              type: 'error',
              message: error.errors[0].message,
              rowKey: key,
            })
          })
      })
    }

    getStatus()

    return () => clearTimeout(tmOut)
  }, [uuids, key, type, setNotification])

  const onChangeFiles = (e) => {
    setStatus([])
    setNotification({})

    if (e.target.files) {
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

  const onSubmit = (event) => {
    const promise = type === IMPORT_TYPE.tmx ? uploadTm : uploadGlossary
    promise({filesToUpload: files, tmKey: row.key, keyName: row.name})
      .then(({data}) =>
        setUuids(
          data.uuids.map((item) => ({
            ...item,
            filename: type === IMPORT_TYPE.tmx ? item.name : files[0].name,
          })),
        ),
      )
      .catch((errors) => onErrorUpload(errors))

    setUuids([])
    event.preventDefault()
  }

  const onReset = () => {
    setFiles([])
    setUuids(undefined)
    setStatus([])
    setNotification({})

    if (!uuids?.length) onCloseRef.current()
  }

  const onErrorUpload = (error) => {
    setNotification({
      type: 'error',
      message: error.errors[0].message,
      rowKey: key,
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
