import React, {useContext, useState} from 'react'
import PropTypes from 'prop-types'
import {
  SPECIAL_ROWS_ID,
  TranslationMemoryGlossaryTabContext,
} from './TranslationMemoryGlossaryTab'
import {tmCreateRandUser} from '../../../../api/tmCreateRandUser'
import {createNewTmKey} from '../../../../api/createNewTmKey'
import {checkTMKey} from '../../../../api/checkTMKey'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {getInfoTmKey} from '../../../../api/getInfoTmKey'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'

export const TMCreateResourceRow = ({row}) => {
  const {tmKeys, setTmKeys} = useContext(SettingsPanelContext)
  const {setSpecialRows, setNotification} = useContext(
    TranslationMemoryGlossaryTabContext,
  )

  const [isLookup, setIsLookup] = useState(row.r ?? false)
  const [isUpdating, setIsUpdating] = useState(row.w ?? false)
  const [name, setName] = useState(row.name ?? '')
  const [keyCode, setKeyCode] = useState('')
  const [submitCheckErrors, setSubmitCheckErrors] = useState()

  const onChangeIsLookup = (e) => {
    const isLookup = e.currentTarget.checked
    setIsLookup(isLookup)
    updateRow({isUpdating, isLookup, name})
  }

  const onChangeIsUpdating = (e) => {
    const isUpdating = e.currentTarget.checked
    setIsUpdating(isUpdating)
    updateRow({isUpdating, isLookup, name})
  }

  const onChangeName = (e) => {
    const {value: name} = e.currentTarget ?? {}
    setName(name)
    if (name) updateRow({isUpdating, isLookup, name})
    setSubmitCheckErrors(undefined)
  }

  const onChangeKeyCode = (e) => {
    setKeyCode(e.currentTarget.value)
    setSubmitCheckErrors(undefined)
    setNotification({})
  }

  const updateRow = ({isLookup, isUpdating, name, keyCode}) => {
    setSpecialRows((prevState) =>
      prevState.map((specialRow) =>
        specialRow.id === row.id
          ? {
              ...specialRow,
              name,
              key: keyCode,
              r: isLookup,
              w: isUpdating,
            }
          : specialRow,
      ),
    )
  }

  const onReset = () => {
    setSpecialRows((prevState) =>
      prevState.filter(
        ({id}) =>
          id !== SPECIAL_ROWS_ID.addSharedResource &&
          id !== SPECIAL_ROWS_ID.newResource,
      ),
    )

    setSubmitCheckErrors(undefined)
    setNotification({})
  }

  const onSubmit = (e) => {
    e.preventDefault()
    const isValid = validateForm()
    if (!isValid) return

    if (row.id === SPECIAL_ROWS_ID.newResource) createNewResource()
    else addSharedResource()
  }

  const validateForm = () => {
    setSubmitCheckErrors(Symbol())
    if (
      !name ||
      (row.id === SPECIAL_ROWS_ID.addSharedResource && (!name || !keyCode))
    )
      return false

    if (row.id === SPECIAL_ROWS_ID.addSharedResource) {
      if (!checkSharedKey()) return false
    }

    return true
  }

  const getNewItem = (key) => ({
    r: isLookup,
    w: isUpdating,
    tm: true,
    glos: true,
    owner: true,
    name,
    key,
    is_shared: false,
    id: key,
    isActive: true,
  })

  const createNewResource = () => {
    tmCreateRandUser().then((response) => {
      const {key} = response.data

      if (config.isLoggedIn) {
        createNewTmKey({
          key,
          description: name,
        })
          .then(() => {
            setTmKeys((prevState) => [getNewItem(key), ...prevState])
            onReset()
          })
          .catch((errors) => {
            setNotification({
              type: 'error',
              message:
                errors[0].code === '23000'
                  ? 'The key you entered is invalid.'
                  : errors[0].message,
            })
          })
      } else {
        setTmKeys((prevState) => [...(prevState ?? []), getNewItem(key)])
        onReset()
      }
    })
  }

  const addSharedResource = () => {
    const key = keyCode

    const createNewTmKeyCallback = () =>
      createNewTmKey({
        key,
        description: name,
      })
        .then(() => {
          setTmKeys((prevState) => [getNewItem(key), ...prevState])
          onReset()
          getInfoTmKeyCallback()
        })
        .catch((errors) => {
          setNotification({
            type: 'error',
            message:
              errors[0].code === '23000'
                ? 'The key you entered is invalid.'
                : errors[0].message,
          })
        })

    const getInfoTmKeyCallback = () => {
      getInfoTmKey({
        key,
      }).then((response) => {
        const users = response.data
        if (users.length > 1)
          setTmKeys((prevState) =>
            prevState.map((tm) =>
              tm.key === key ? {...tm, is_shared: true} : tm,
            ),
          )
      })
    }

    checkTMKey({
      tmKey: key,
    })
      .then((data) => {
        if (data.success === true) createNewTmKeyCallback()
      })
      .catch(() => {
        setNotification({
          type: 'error',
          message: 'The key you entered is invalid.',
        })
      })
  }

  const checkSharedKey = (dispathNotification = true) => {
    if (tmKeys.filter(({owner}) => owner).find(({key}) => key === keyCode)) {
      if (dispathNotification) {
        setNotification({
          type: 'error',
          message:
            'The key is already assigned to one of your Inactive TMs. <a class="active-tm-key-link activate-key">Click here to activate it</a>',
        })
      }
      return false
    }
    return true
  }

  const inputNameClasses = `tm-key-create-resource-row-input ${
    typeof submitCheckErrors === 'symbol' && !name ? ' error' : ''
  }`
  const inputKeyCodeClasses = `tm-key-create-resource-row-input ${
    (typeof submitCheckErrors === 'symbol' && !keyCode) ||
    (keyCode && !checkSharedKey(false))
      ? ' error'
      : ''
  }`

  return (
    <form className="settings-panel-row-content" onSubmit={onSubmit}>
      <div className="tm-key-lookup align-center">
        <input checked={isLookup} onChange={onChangeIsLookup} type="checkbox" />
      </div>
      <div className="tm-key-update align-center">
        <input
          checked={isUpdating}
          onChange={onChangeIsUpdating}
          type="checkbox"
        />
      </div>
      <div>
        <input
          placeholder="Please insert a name for the resource"
          className={inputNameClasses}
          value={name}
          onChange={onChangeName}
        ></input>
      </div>
      <div>
        {row.id === SPECIAL_ROWS_ID.addSharedResource && (
          <input
            placeholder="Add the shared key here"
            className={inputKeyCodeClasses}
            value={keyCode}
            onChange={onChangeKeyCode}
          ></input>
        )}
      </div>
      <div />
      <div className="translation-memory-glossary-tab-buttons-group">
        <button
          className="ui primary button settings-panel-button-icon tm-key-small-row-button"
          type="submit"
        >
          <Checkmark size={16} />
          Confirm
        </button>
        <button
          className="ui button orange tm-key-small-row-button"
          onClick={onReset}
          type="reset"
        >
          <Close />
        </button>
      </div>
    </form>
  )
}

TMCreateResourceRow.propTypes = {
  row: PropTypes.object.isRequired,
}
