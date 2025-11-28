import React, {
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../common/Button/Button'
import AddWide from '../../../../../img/icons/AddWide'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import {Select} from '../../../common/Select'
import {InputPercentage} from './InputPercentage'
import IconClose from '../../../icons/IconClose'
import Checkmark from '../../../../../img/icons/Checkmark'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {cloneDeep, isEqual} from 'lodash'
import {v4 as uuidV4} from 'uuid'
import IconAdd from '../../../icons/IconAdd'

export const LanguagesExceptions = ({breakdowns, updateExceptions}) => {
  const {analysisTemplates} = useContext(SettingsPanelContext)
  const {languages: originalLanguages} = useContext(CreateProjectContext)

  const {templates, currentTemplate} = analysisTemplates

  const [exceptions, setExceptions] = useState([])

  const [pendingExceptionsId, setPendingExceptionsId] = useState([])

  const languages = useMemo(
    () =>
      originalLanguages.reduce((acc, cur) => {
        const code = getLanguageCode(cur.code)

        if (acc.some((lang) => lang.code === code)) return acc

        const regions = acc.filter(
          (lang) => getLanguageCode(lang.code) === code,
        )
        const accFiltered = acc.filter(
          (lang) => !regions.some(({code}) => lang.code === code),
        )

        const result =
          regions.length > 1
            ? [
                {
                  ...cur,
                  code,
                  name: `${cur.name.split('(')[0]} (All variants)`,
                  id: code,
                },
                ...regions,
              ]
            : [cur]

        return [...accFiltered, ...result]
      }, originalLanguages),
    [originalLanguages],
  )

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
      if (source !== 'default') {
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
  }, [breakdowns])

  useEffect(() => {
    evaluateExceptions()
  }, [evaluateExceptions, breakdowns])

  const addException = ({source, target, value, id}) => {
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

    setPendingExceptionsId((prevState) =>
      prevState.filter((exceptionId) => exceptionId !== id),
    )
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
            languages={languages}
            addException={(newException) => modifyException(item, newException)}
            removeException={() => removeException(item)}
            key={item.source + '-' + item.target}
            confirmed={true}
            isExceptionSaved={isExceptionSaved}
          />
        )
      })}
      {pendingExceptionsId.map((exceptionId) => (
        <LanguageException
          languages={languages}
          addException={(props) => addException({...props, id: exceptionId})}
          key={'newExc' + exceptionId}
          removeException={() => {
            setPendingExceptionsId((prevState) =>
              prevState.filter(
                (prevExceptionId) => prevExceptionId !== exceptionId,
              ),
            )
          }}
          isExceptionSaved={isExceptionSaved}
        />
      ))}
      <Button
        className="add-button"
        type={BUTTON_TYPE.PRIMARY}
        size={BUTTON_SIZE.MEDIUM}
        onClick={() =>
          setPendingExceptionsId((prevState) => [...prevState, uuidV4()])
        }
      >
        <IconAdd size={20} />
        Add exception
      </Button>
    </div>
  )
}

LanguagesExceptions.propTypes = {
  breakdowns: PropTypes.object,
  updateExceptions: PropTypes.func,
}

const getLanguageCode = (code) => code.split('-')[0]

const LanguageException = ({
  exception,
  languages,
  confirmed = false,
  addException,
  removeException,
  isExceptionSaved,
}) => {
  const [source, setSource] = useState(
    exception
      ? (languages.find((l) => exception.source === l.id) ??
          languages.find((l) => exception.source === getLanguageCode(l.id)))
      : undefined,
  )
  const [target, setTarget] = useState(
    exception
      ? (languages.find((l) => exception.target === l.id) ??
          languages.find((l) => exception.target === getLanguageCode(l.id)))
      : undefined,
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
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-portal"
          showSearchBar={true}
          options={languages}
          onSelect={(option) => {
            setSource(option)
            setModified(true)
          }}
          placeholder={'Please select language'}
          activeOption={source}
        >
          {({name, code}) => ({
            row: (
              <div className="language-dropdown-item-container">
                <div className="code-badge">
                  <span>{code}</span>
                </div>

                <span>{name}</span>
              </div>
            ),
          })}
        </Select>
        {/*TODO swap lingue*/}
        <div id="swaplang" title="Swap languages" onClick={swapLanguages} />
        <Select
          name={'lang'}
          isPortalDropdown={true}
          dropdownClassName="select-dropdown__wrapper-portal"
          showSearchBar={true}
          options={languages}
          onSelect={(option) => {
            setTarget(option)
            setModified(true)
          }}
          placeholder={'Please select language'}
          activeOption={target}
        >
          {({name, code}) => ({
            row: (
              <div className="language-dropdown-item-container">
                <div className="code-badge">
                  <span>{code}</span>
                </div>

                <span>{name}</span>
              </div>
            ),
          })}
        </Select>
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
  languages: PropTypes.array,
  confirmed: PropTypes.bool,
  addException: PropTypes.func,
  removeException: PropTypes.func,
  isExceptionSaved: PropTypes.func,
}
