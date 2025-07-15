import {useRef, useState, useEffect} from 'react'
import PropTypes from 'prop-types'
import {downloadTMX} from '../../../../../api/downloadTMX'
import {downloadGlossary} from '../../../../../api/downloadGlossary'
import CatToolActions from '../../../../../actions/CatToolActions'

export const EXPORT_TYPE = {
  tmx: 'tmx',
  glossary: 'glossary',
}

function useExport({type, row, onClose}) {
  const [email, setEmail] = useState(config.userMail)
  const [status, setStatus] = useState()

  const tmOutOnCloseRef = useRef()

  useEffect(() => {
    return () => clearTimeout(tmOutOnCloseRef.current)
  }, [])

  const onChange = (e) => {
    setStatus(undefined)
    if (e.currentTarget.value) setEmail(e.currentTarget.value)
  }

  const onSubmit = (event, stripTags = undefined) => {
    const promise = type === EXPORT_TYPE.tmx ? downloadTMX : downloadGlossary
    promise({key: row.key, name: row.name, stripTags})
      .then(() => {
        setStatus({successfull: true})
        tmOutOnCloseRef.current = setTimeout(onClose, 2000)
      })
      .catch((errors) => {
        const errorsObject = errors?.[0]
          ? errors[0]
          : {message: 'We got an error, please contact support'}
        CatToolActions.addNotification({
          title: 'Error export',
          type: 'error',
          text: errorsObject.message,
          position: 'br',
          allowHtml: true,
          timer: 5000,
        })
        setStatus({errors: errorsObject})
      })

    event.preventDefault()
  }

  const onReset = () => {
    setEmail(config.userMail)
    setStatus(undefined)

    onClose()
  }

  return {email, status, onChange, onSubmit, onReset}
}

useExport.propTypes = {
  type: PropTypes.oneOf(Object.values(EXPORT_TYPE)).isRequired,
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}

export default useExport
