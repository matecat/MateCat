import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {
  SPECIAL_ROWS_ID,
  TranslationMemoryGlossaryTabContext,
  orderTmKeys,
} from './TranslationMemoryGlossaryTab'
import {tmCreateRandUser} from '../../../../api/tmCreateRandUser'
import {createNewTmKey} from '../../../../api/createNewTmKey'
import {checkTMKey} from '../../../../api/checkTMKey'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {getInfoTmKey} from '../../../../api/getInfoTmKey'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'
import CatToolActions from '../../../../actions/CatToolActions'

export const TMCreateResourceRow = ({row}) => {
  const {tmKeys, setTmKeys, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)
  const {setSpecialRows} = useContext(TranslationMemoryGlossaryTabContext)

  const [isLookup, setIsLookup] = useState(row.r ?? false)
  const [isUpdating, setIsUpdating] = useState(row.w ?? false)
  const [name, setName] = useState(row.name ?? '')
  const [keyCode, setKeyCode] = useState('')

  const nameRef = useRef()

  useEffect(() => {
    nameRef.current.focus()
  }, [])

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
  }

  const onChangeKeyCode = (e) => {
    setKeyCode(e.currentTarget.value)
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
  }

  const onSubmit = (e) => {
    e.preventDefault()
    const isValid = validateForm()
    if (!isValid) return

    if (row.id === SPECIAL_ROWS_ID.newResource) createNewResource()
    else addSharedResource()
  }

  const validateForm = () => {
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

  const executeModifyCurrentTemplate = (updatedKeys) => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      tm: orderTmKeys(
        updatedKeys.filter(({isActive}) => isActive),
        prevTemplate.tm.map(({key}) => key),
      ).map(({id, isActive, ...rest}) => rest), //eslint-disable-line
    }))
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
    isActive: isLookup ? isLookup : !isLookup && !isUpdating ? false : true,
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
            const updatedKeys = [getNewItem(key), ...tmKeys]
            setTmKeys(updatedKeys)
            executeModifyCurrentTemplate(updatedKeys)
            onReset()
            CatToolActions.addNotification({
              title: 'Resource created ',
              type: 'success',
              text: `Resource <b>${name}</b> created successfully`,
              position: 'br',
              allowHtml: true,
              timer: 5000,
            })
          })
          .catch((errors) => {
            CatToolActions.addNotification({
              title: 'Invalid key',
              type: 'error',
              text:
                !errors || errors[0].code === '23000'
                  ? 'The key you entered is invalid.'
                  : errors[0].message,
              position: 'br',
              timer: 5000,
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
          const updatedKeys = [getNewItem(key), ...tmKeys]
          setTmKeys(updatedKeys)
          executeModifyCurrentTemplate(updatedKeys)
          onReset()
          getInfoTmKeyCallback()
        })
        .catch((errors) => {
          CatToolActions.addNotification({
            title: 'Invalid key',
            type: 'error',
            text:
              errors[0].code === '23000'
                ? 'The key you entered is invalid.'
                : errors[0].message,
            position: 'br',
            timer: 5000,
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
        CatToolActions.addNotification({
          title: 'Invalid key',
          type: 'error',
          text: 'The key you entered is invalid.',
          position: 'br',
          timer: 5000,
        })
      })
  }

  const activateInactiveSharedKey = ({event, rowAlreadyAssigned}) => {
    onReset()

    const updatedKeys = tmKeys.map((tm) =>
      tm.id === rowAlreadyAssigned.id
        ? {
            ...tm,
            isActive: true,
            r: true,
            w: true,
          }
        : tm,
    )
    setTmKeys(updatedKeys)
    executeModifyCurrentTemplate(updatedKeys)

    event.preventDefault()
  }

  const checkSharedKey = (dispathNotification = true) => {
    const rowAlreadyAssigned = tmKeys
      .filter(({owner}) => owner)
      .find(({key}) => key === keyCode)

    if (rowAlreadyAssigned) {
      const message = rowAlreadyAssigned.isActive
        ? 'The key is already assigned to one of your Active TMs.'
        : 'The key is already assigned to one of your Inactive TMs.'

      if (dispathNotification) {
        CatToolActions.addNotification({
          title: 'Invalid key',
          type: 'error',
          text: message,
          position: 'br',
          timer: 5000,
        })
      }

      return false
    }
    return true
  }

  const inputNameClasses = 'tm-key-create-resource-row-input'
  const inputKeyCodeClasses = 'tm-key-create-resource-row-input'

  const isFormFilled =
    row.id === SPECIAL_ROWS_ID.addSharedResource ? keyCode && name : name

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
          ref={nameRef}
          placeholder="Please insert a name for the resource"
          className={inputNameClasses}
          value={name}
          onChange={onChangeName}
          data-testid={row.id}
        />
      </div>
      <div>
        {row.id === SPECIAL_ROWS_ID.addSharedResource && (
          <input
            placeholder="Add the shared key here"
            className={inputKeyCodeClasses}
            value={keyCode}
            onChange={onChangeKeyCode}
            data-testid={`input-${row.id}`}
          ></input>
        )}
      </div>
      <div />
      <div className="translation-memory-glossary-tab-buttons-group">
        <button
          className="ui primary button settings-panel-button-icon confirm-button"
          type="submit"
          disabled={!isFormFilled}
          data-testid="create-tmkey-confirm"
        >
          <Checkmark size={12} />
          Confirm
        </button>
        <button
          className="ui button orange close-button"
          onClick={onReset}
          type="reset"
        >
          <Close size={18} />
        </button>
      </div>
    </form>
  )
}

TMCreateResourceRow.propTypes = {
  row: PropTypes.object.isRequired,
}
