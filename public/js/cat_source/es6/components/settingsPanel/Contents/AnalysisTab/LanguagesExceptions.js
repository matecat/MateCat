import React, {useCallback, useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../common/Button/Button'
import AddWide from '../../../../../../../img/icons/AddWide'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import {Select} from '../../../common/Select'
import {InputPercentage} from './InputPercentage'
import IconClose from '../../../icons/IconClose'
import Checkmark from '../../../../../../../img/icons/Checkmark'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {cloneDeep, isEqual} from 'lodash'

export const LanguagesExceptions = ({breakdowns, updateExceptions}) => {
  const {analysisTemplates} = useContext(SettingsPanelContext)
  const {templates, currentTemplate} = analysisTemplates

  const [exceptions, setExceptions] = useState([])

  const [addExceptionCounter, setAddExceptionCounter] = useState(0)

  const originalCurrentTemplate = templates?.find(
    ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
  )
  const isExceptionSaved = ({source, target, data}) =>
    originalCurrentTemplate.breakdowns[source] &&
    originalCurrentTemplate.breakdowns[source][target] &&
    isEqual(originalCurrentTemplate.breakdowns[source][target], data)

  const evaluateExceptions = useCallback(() => {
    let exceptions = []
    for (const [source, value] of Object.entries(breakdowns)) {
      if (source !== 'default' && source.indexOf('-') > -1) {
        for (const [target, data] of Object.entries(value)) {
          exceptions.push({
            source: source,
            target: target,
            data: data,
          })
        }
      }
    }
    setExceptions(exceptions)
    setAddExceptionCounter(exceptions.length ? 0 : 1)
  }, [breakdowns])

  useEffect(() => {
    evaluateExceptions()
  }, [evaluateExceptions, breakdowns])

  const addException = ({source, target, value}) => {
    const newException = {
      [source.code]: {
        ...breakdowns[source.code],
        [target.code]: {
          ...breakdowns.default,
          MT: value,
        },
      },
    }
    updateExceptions({
      ...breakdowns,
      ...newException,
    })
  }
  const removeException = (exception) => {
    let newObject = cloneDeep(breakdowns)
    delete newObject[exception.source][exception.target]
    if (Object.keys(newObject[exception.source]).length === 0) {
      delete newObject[exception.source]
    }
    updateExceptions({
      ...newObject,
    })
  }
  const modifyException = (oldException, {source, target, value}) => {
    let newObject = {...breakdowns}
    delete newObject[oldException.source][oldException.target]
    if (Object.keys(newObject[oldException.source]).length === 0) {
      delete newObject[oldException.source]
    }
    const newException = {
      [source.code]: {
        ...breakdowns[source.code],
        [target.code]: {
          ...breakdowns.default,
          MT: value,
        },
      },
    }
    updateExceptions({
      ...newObject,
      ...newException,
    })
  }

  return (
    <div className="analysis-tab-exceptions">
      <h3>Exceptions</h3>
      {exceptions.map((item) => {
        return (
          <LanguageException
            exception={item}
            addException={(newException) => modifyException(item, newException)}
            removeException={() => removeException(item)}
            key={item.source + '-' + item.target}
            confirmed={true}
            isExceptionSaved={isExceptionSaved}
          />
        )
      })}
      {[...Array(addExceptionCounter)].map((e, i) => (
        <LanguageException
          addException={addException}
          key={'newExc' + i}
          removeException={() => {
            setAddExceptionCounter((prevState) => prevState - 1)
          }}
          isExceptionSaved={isExceptionSaved}
        />
      ))}
      <Button
        className="add-button"
        type={BUTTON_TYPE.PRIMARY}
        size={BUTTON_SIZE.MEDIUM}
        onClick={() => setAddExceptionCounter((prevState) => prevState + 1)}
      >
        <AddWide size={12} />
        Add exception
      </Button>
    </div>
  )
}

LanguagesExceptions.propTypes = {
  breakdowns: PropTypes.object,
  updateExceptions: PropTypes.func,
}
const LanguageException = ({
  exception,
  confirmed = false,
  addException,
  removeException,
  isExceptionSaved,
}) => {
  const {languages} = useContext(CreateProjectContext)
  const [source, setSource] = useState(
    exception ? languages.find((l) => exception.source === l.id) : undefined,
  )
  const [target, setTarget] = useState(
    exception ? languages.find((l) => exception.target === l.id) : undefined,
  )
  const [value, setValue] = useState(exception ? exception.data.MT : undefined)
  const [modified, setModified] = useState(false)
  const swapLanguages = () => {
    setSource(target)
    setTarget(source)
    setModified(true)
  }
  return (
    <div
      className={`analysis-tab-exceptionsRow ${exception && !isExceptionSaved(exception) ? 'analysis-value-not-saved' : ''}`}
    >
      <div className="analysis-tab-languages">
        <Select
          name={'lang'}
          showSearchBar={true}
          options={languages}
          onSelect={(option) => {
            setSource(option)
            setModified(true)
          }}
          placeholder={'Please select language'}
          activeOption={source}
        />
        {/*TODO swap lingue*/}
        <div id="swaplang" title="Swap languages" onClick={swapLanguages} />
        <Select
          name={'lang'}
          showSearchBar={true}
          options={languages}
          onSelect={(option) => {
            setTarget(option)
            setModified(true)
          }}
          placeholder={'Please select language'}
          activeOption={target}
        />
      </div>
      <InputPercentage
        value={value}
        setFn={(value) => {
          setValue(value)
          setModified(true)
        }}
      />
      {confirmed && !modified ? (
        <div className="analysis-tab-buttons">
          <Button
            size={BUTTON_SIZE.SMALL}
            mode={BUTTON_MODE.GHOST}
            onClick={removeException}
          >
            <IconClose />
          </Button>
        </div>
      ) : (
        <div className="analysis-tab-buttons">
          <Button
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.MEDIUM}
            disabled={source && target && value ? false : true}
            className={'confirm-button'}
            onClick={() => {
              addException({source, target, value})
              setModified(false)
            }}
          >
            <Checkmark size={12} />
            Confirm
          </Button>
          <Button
            type={BUTTON_TYPE.WARNING}
            size={BUTTON_SIZE.MEDIUM}
            className="close-button"
            onClick={removeException}
          >
            <IconClose />
          </Button>
        </div>
      )}
    </div>
  )
}

LanguageException.propTypes = {
  exception: PropTypes.object,
  confirmed: PropTypes.bool,
  addException: PropTypes.func,
  removeException: PropTypes.func,
  isExceptionSaved: PropTypes.func,
}
