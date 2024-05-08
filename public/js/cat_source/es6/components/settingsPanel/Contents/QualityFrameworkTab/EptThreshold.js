import React, {useContext, useRef} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'

export const EptThreshold = () => {
  const {currentTemplate, templates, modifyingCurrentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const refR1 = useRef()
  const refR2 = useRef()
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

  const selectAll = ({current}) => current.select()

  const originalCurrentTemplate = templates?.find(
    ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
  )
  const isR1NotSaved =
    originalCurrentTemplate.passfail.thresholds.find(
      ({label}) => label === 'R1',
    ).value !== thresholdR1
  const isR2NotSaved =
    originalCurrentTemplate.passfail.thresholds.find(
      ({label}) => label === 'R2',
    ).value !== thresholdR2

  return (
    <div>
      <h2>EPT Threshold</h2>
      <p>
        Select the EPT thresholds for a project to be considered a
        &quot;Pass&quot; or a &quot;Fail&quot; in both R1 and R2. Lower EPT
        means better quality.
      </p>
      <div className="quality-framework-box-ept-threshold">
        <div>
          <label>R1</label>
          <input
            ref={refR1}
            className={`quality-framework-input${isR1NotSaved ? ' quality-framework-not-saved' : ''}`}
            type="text"
            value={thresholdR1}
            onChange={setThresholdR1}
            onFocus={() => selectAll(refR1)}
            onBlur={checkInput}
            data-testid="threshold-R1"
          />
        </div>
        <div>
          <label>R2</label>
          <input
            ref={refR2}
            className={`quality-framework-input${isR2NotSaved ? ' quality-framework-not-saved' : ''}`}
            type="text"
            value={thresholdR2}
            onChange={setThresholdR2}
            onFocus={() => selectAll(refR2)}
            onBlur={checkInput}
            data-testid="threshold-R2"
          />
        </div>
      </div>
    </div>
  )
}
