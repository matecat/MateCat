import React, {createContext, useContext, useEffect, useRef, useState} from 'react'
import Switch from '../../../common/Switch'
import {getBillingModelTemplates} from '../../../../api/getBillingModelTemplates'
import useTemplates from '../../../../hooks/useTemplates'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {createBillingModelTemplate} from '../../../../api/createBillingModelTemplate'
import {updateBillingModelTemplate} from '../../../../api/updateBillingModelTemplate'
import {deleteBillingModelTemplate} from '../../../../api/deleteBillingModelTemplate'
import {SubTemplates} from '../SubTemplates'

const ANALYSIS_SCHEMA_KEYS = {
  id: 'id',
  uid: 'uid',
  name: 'payable_rate_template_name',
  breakdowns: 'breakdowns',
  createdAt: 'createdAt',
  modifiedAt: 'modifiedAt',
  version: 'version',
}

const getFilteredSchemaCreateUpdate = (template) => {
  /* eslint-disable no-unused-vars */
  const {
    id,
    uid,
    version,
    createdAt,
    modifiedAt,
    isTemporary,
    isSelected,
    ...filtered
  } = template
  /* eslint-enable no-unused-vars */
  return filtered
}

export const AnalysisTabContext = createContext({})

export const AnalysisTab = () => {
  const {
    currentProjectTemplate,
    modifyingCurrentTemplate: modifyingCurrentProjectTemplate,
  } = useContext(SettingsPanelContext)

  const {templates, setTemplates, currentTemplate, modifyingCurrentTemplate} =
    useTemplates(ANALYSIS_SCHEMA_KEYS)

  const [matches100InScope, setMatches100InScope] = useState(true)
  const [matches101InScope, setMatches101InScope] = useState(true)
  const [newValue, setNewValue] = useState('100')
  const [repetitions, setRepetitions] = useState('100')

  const currentTemplateId = currentTemplate?.id
  const currentProjectTemplateBillingId =
      currentProjectTemplate.payableRateTemplateId
  const prevCurrentProjectTemplateBillingId = useRef()

  // select billing model template when curren project template change
  if (
      prevCurrentProjectTemplateBillingId.current !==
      currentProjectTemplateBillingId &&
      currentTemplateId !== currentProjectTemplateBillingId &&
      templates.length
  ) {
    setTemplates((prevState) =>
        prevState.map((template) => ({
          ...template,
          isSelected: template.id === currentProjectTemplateBillingId,
        })),
    )
  }

  prevCurrentProjectTemplateBillingId.current = currentProjectTemplateBillingId


  // retrieve billing model templates
  useEffect(() => {
    let cleanup = false

    if (config.isLoggedIn === 1 && !config.is_cattool) {
      getBillingModelTemplates().then(({items}) => {
        if (!cleanup) {
          setTemplates(
              items.map((template) => ({
                ...template,
                isSelected:
                    template.id === prevCurrentProjectTemplateBillingId.current,
              })),
          )
        }
      })
    } else {
      // not logged in
    }

    return () => (cleanup = true)
  }, [setTemplates])

  return (
    <div>
      <SubTemplates
        {...{
          templates,
          setTemplates,
          currentTemplate,
          modifyingCurrentTemplate,
          schema: ANALYSIS_SCHEMA_KEYS,
          getFilteredSchemaCreateUpdate,
          createApi: createBillingModelTemplate,
          updateApi: updateBillingModelTemplate,
          deleteApi: deleteBillingModelTemplate,
        }}
      />
      <div className="settings-panel-contentwrapper-tab-background">
        <div>
          <h2>Pre-translate settings</h2>
          <span>
            Select whether 100%/101% matches are in-scope for the job. If they
            are out of scope, their payable rate will be set to 0% and they will
            be preapproved and locked in the editor window
          </span>
        </div>
        <div>
          <div>
            <h3>100% matches</h3>
            <Switch
              onChange={(value) => {
                setMatches100InScope(value)
              }}
              active={matches100InScope}
              activeText={'In scope'}
              inactiveText={'Not in scope'}
            />
          </div>
          <div>
            <h3>101% matches</h3>
            <Switch
              onChange={(value) => {
                setMatches101InScope(value)
              }}
              active={matches101InScope}
              activeText={'In scope'}
              inactiveText={'Not in scope'}
            />
          </div>
        </div>
        <div>
          <table>
            <thead>
              <tr>
                <th>New</th>
                <th>Repetitions</th>
                <th>Internal matches 75-99%</th>
                <th>TM Partial 50-74%</th>
                <th>TM Partial 75-84%</th>
                <th>TM Partial 85-94%</th>
                <th>TM Partial 95-99%</th>
                <th>TM 100%</th>
                <th>Public TM 100%</th>
                <th>TM 100% in context</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <InputPercentage value={newValue} setFn={setNewValue} />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
const InputPercentage = ({value, setFn}) => {
  const inputRef = useRef()
  const onPercentInput = (e) => {
    let int = e.target.value.split('%')[0]
    int = parseInt(int)
    int = isNaN(int) ? 0 : int
    if (int > 100) {
      int = 100
    }
    setFn(int)
  }
  return (
    <input
      ref={inputRef}
      value={value + '%'}
      onInput={(e) => onPercentInput(e)}
    />
  )
}
