import React, {useContext} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'

export const EptThreshold = () => {
  const {currentTemplate, modifyingCurrentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const getThreshold = (type) => {
    const value = currentTemplate?.passfail.thresholds.find(
      ({label}) => label === type,
    ).value
    return typeof value === 'number' ? value.toFixed(2) : value
  }
  const setThreshold = (type, value) =>
    modifyingCurrentTemplate((prevTemplate) => {
      const isValidInput = !/[^+0-9.]/g.test(value)
      const {thresholds} = prevTemplate.passfail

      return {
        ...prevTemplate,
        passfail: {
          ...prevTemplate.passfail,
          thresholds: thresholds.map((item) =>
            item.label === type
              ? {
                  ...item,
                  value: isValidInput
                    ? value
                    : !isValidInput && value.length === 0
                      ? ''
                      : item.value,
                }
              : item,
          ),
        },
      }
    })

  const thresholdR1 = getThreshold('T')
  const setThresholdR1 = ({currentTarget: {value}}) => setThreshold('T', value)

  const thresholdR2 = getThreshold('R1')
  const setThresholdR2 = ({currentTarget: {value}}) => setThreshold('R1', value)

  const setThresholdToNumber = () =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      passfail: {
        ...prevTemplate.passfail,
        thresholds: prevTemplate.passfail.thresholds.map((item) => ({
          ...item,
          value: parseFloat(item.value ? item.value : 0),
        })),
      },
    }))

  return (
    <div>
      <h2>EPT Threshold</h2>
      <div className="quality-framework-box-ept-threshold">
        <div>
          <label>R1</label>
          <input
            value={thresholdR1}
            onChange={setThresholdR1}
            onBlur={setThresholdToNumber}
          />
        </div>
        <div>
          <label>R2</label>
          <input
            value={thresholdR2}
            onChange={setThresholdR2}
            onBlur={setThresholdToNumber}
          />
        </div>
      </div>
    </div>
  )
}
