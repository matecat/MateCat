import React, {useEffect, useRef} from 'react'
import useTemplates from '../../../../hooks/useTemplates'
import {SubTemplates} from '../SubTemplates'
import {useContext} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {getQualityFrameworkTemplates} from '../../../../api/getQualityFrameworkTemplates/getQualityFrameworkTemplates'

const QF_SCHEMA_KEYS = {
  id: 'id',
  uid: 'uid',
  name: 'label',
  version: 'version',
  categories: 'categories',
  passfail: 'passfail',
}

export const QualityFrameworkTab = () => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

  const {templates, setTemplates, currentTemplate, modifyingCurrentTemplate} =
    useTemplates(QF_SCHEMA_KEYS)

  const thresholdR1 = currentTemplate?.passfail.thresholds.find(
    ({label}) => label === 'T',
  ).value
  const setThresholdR1 = (value) =>
    modifyingCurrentTemplate((prevTemplate) => {
      const {thresholds} = prevTemplate.passfail

      return {
        ...prevTemplate,
        passfail: {
          ...prevTemplate.passfail,
          thresholds: thresholds.map((item) =>
            item.label === 'T' ? {...item, value} : item,
          ),
        },
      }
    })

  const thresholdR2 = currentTemplate?.passfail.thresholds.find(
    ({label}) => label === 'R1',
  ).value
  const setThresholdR2 = (value) =>
    modifyingCurrentTemplate((prevTemplate) => {
      const {thresholds} = prevTemplate.passfail

      return {
        ...prevTemplate,
        passfail: {
          ...prevTemplate.passfail,
          thresholds: thresholds.map((item) =>
            item.label === 'R1' ? {...item, value} : item,
          ),
        },
      }
    })

  const currentProjectTemplateQaId =
    currentProjectTemplate.qaModelTemplateId ?? 0
  const qaModelTemplateId = useRef()

  if (
    qaModelTemplateId.current !== currentProjectTemplateQaId &&
    templates.length
  ) {
    // select right QF template when curren project template change
  }

  qaModelTemplateId.current = currentProjectTemplateQaId

  useEffect(() => {
    let cleanup = false

    if (config.isLoggedIn === 1 && !config.is_cattool) {
      getQualityFrameworkTemplates().then(({items}) => {
        if (!cleanup) {
          setTemplates(
            items.map((template) => ({
              ...template,
              isSelected: template.id === qaModelTemplateId.current,
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
    <div className="quality-framework-box">
      <SubTemplates
        {...{
          templates,
          setTemplates,
          currentTemplate,
          modifyingCurrentTemplate,
        }}
      />
      <div className="settings-panel-contentwrapper-tab-background">
        <h2>EPT Threshold</h2>
        <div className="quality-framework-box-ept-threshold">
          <div>
            <label>R1</label>
            <input
              value={thresholdR1}
              onChange={(e) => setThresholdR1(parseInt(e.currentTarget.value))}
            />
          </div>
          <div>
            <label>R2</label>
            <input
              value={thresholdR2}
              onChange={(e) => setThresholdR2(parseInt(e.currentTarget.value))}
            />
          </div>
        </div>
      </div>
    </div>
  )
}
