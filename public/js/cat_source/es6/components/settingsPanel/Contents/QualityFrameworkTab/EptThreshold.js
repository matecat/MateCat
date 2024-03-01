import React, {useContext, useRef} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'

export const EptThreshold = () => {
  const {currentTemplate, modifyingCurrentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const previousThresholds = useRef({R1: undefined, R2: undefined})

  const getThreshold = (type) => {
    return currentTemplate?.passfail.thresholds.find(
      ({label}) => label === type,
    ).value
  }
  const setThreshold = (type, value) =>
    modifyingCurrentTemplate((prevTemplate) => {
      const isValidInput = typeof value === 'number' || !/[^+0-9]/g.test(value)
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
                    ? value.length > 0 || typeof value === 'number'
                      ? parseInt(value)
                      : ''
                    : item.value,
                }
              : item,
          ),
        },
      }
    })

  const thresholdR1 = getThreshold('R1')
  const setThresholdR1 = ({currentTarget: {value}}) => setThreshold('R1', value)

  const thresholdR2 = getThreshold('R2')
  const setThresholdR2 = ({currentTarget: {value}}) => setThreshold('R2', value)

  const checkInput = () => {
    if (typeof thresholdR1 !== 'number')
      setThreshold('R1', previousThresholds.current.R1)
    if (typeof thresholdR2 !== 'number')
      setThreshold('R2', previousThresholds.current.R2)
  }

  if (typeof thresholdR1 === 'number')
    previousThresholds.current.R1 = thresholdR1
  if (typeof thresholdR2 === 'number')
    previousThresholds.current.R2 = thresholdR2

  return (
    <div>
      <h2>EPT Threshold</h2>
      <div className="quality-framework-box-ept-threshold">
        <div>
          <label>R1</label>
          <input
            value={thresholdR1}
            onChange={setThresholdR1}
            onBlur={checkInput}
          />
        </div>
        <div>
          <label>R2</label>
          <input
            value={thresholdR2}
            onChange={setThresholdR2}
            onBlur={checkInput}
          />
        </div>
      </div>
    </div>
  )
}
