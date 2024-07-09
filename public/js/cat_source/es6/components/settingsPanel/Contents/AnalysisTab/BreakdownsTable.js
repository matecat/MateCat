import React, {useContext} from 'react'
import {InputPercentage} from './InputPercentage'
import {ANALYSIS_BREAKDOWNS} from './AnalysisTab'
import {SettingsPanelContext} from '../../SettingsPanelContext'

export const BreakdownsTable = ({saveValue}) => {
  const {analysisTemplates} = useContext(SettingsPanelContext)
  const {templates, currentTemplate} = analysisTemplates

  const newWords =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.newWords]
  const setNewWords = (value) => saveValue(ANALYSIS_BREAKDOWNS.newWords, value)
  const repetitions =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.repetitions]
  const setRepetitions = (value) =>
    saveValue(ANALYSIS_BREAKDOWNS.repetitions, value)
  const internal75_99 =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.internal75_99]
  const setInternal75_99 = (value) =>
    saveValue(ANALYSIS_BREAKDOWNS.internal75_99, value)
  const tm50_74 =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.tm50_74]
  const setTm50_74 = (value) => saveValue(ANALYSIS_BREAKDOWNS.tm50_74, value)
  const tm75_84 =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.tm75_84]
  const setTm75_84 = (value) => saveValue(ANALYSIS_BREAKDOWNS.tm75_84, value)
  const tm85_94 =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.tm85_94]
  const setTm85_94 = (value) => saveValue(ANALYSIS_BREAKDOWNS.tm85_94, value)
  const tm95_99 =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.tm95_99]
  const setTm95_99 = (value) => saveValue(ANALYSIS_BREAKDOWNS.tm95_99, value)
  const tm100 = currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.tm100]
  const setTm100 = (value) => saveValue(ANALYSIS_BREAKDOWNS.tm100, value)
  const public100 =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.public100]
  const setPublic100 = (value) =>
    saveValue(ANALYSIS_BREAKDOWNS.public100, value)
  const tm100InContext =
    currentTemplate?.breakdowns.default[ANALYSIS_BREAKDOWNS.tm100InContext]
  const setTm100InContext = (value) =>
    saveValue(ANALYSIS_BREAKDOWNS.tm100InContext, value)

  const originalCurrentTemplate = templates?.find(
    ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
  )
  const isValueSaved = (property, value) =>
    originalCurrentTemplate.breakdowns.default[property] === value

  return (
    <div className="analysis-tab-tableContainer">
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
              <InputPercentage
                value={newWords}
                setFn={setNewWords}
                dataTestid={ANALYSIS_BREAKDOWNS.newWords}
                className={
                  !isValueSaved(ANALYSIS_BREAKDOWNS.newWords, newWords)
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={repetitions}
                setFn={setRepetitions}
                dataTestid={ANALYSIS_BREAKDOWNS.repetitions}
                className={
                  !isValueSaved(ANALYSIS_BREAKDOWNS.repetitions, repetitions)
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={internal75_99}
                setFn={setInternal75_99}
                dataTestid={ANALYSIS_BREAKDOWNS.internal75_99}
                className={
                  !isValueSaved(
                    ANALYSIS_BREAKDOWNS.internal75_99,
                    internal75_99,
                  )
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={tm50_74}
                setFn={setTm50_74}
                dataTestid={ANALYSIS_BREAKDOWNS.tm50_74}
                className={
                  !isValueSaved(ANALYSIS_BREAKDOWNS.tm50_74, tm50_74)
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={tm75_84}
                setFn={setTm75_84}
                dataTestid={ANALYSIS_BREAKDOWNS.tm75_84}
                className={
                  !isValueSaved(ANALYSIS_BREAKDOWNS.tm75_84, tm75_84)
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={tm85_94}
                setFn={setTm85_94}
                dataTestid={ANALYSIS_BREAKDOWNS.tm85_94}
                className={
                  !isValueSaved(ANALYSIS_BREAKDOWNS.tm85_94, tm85_94)
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={tm95_99}
                setFn={setTm95_99}
                dataTestid={ANALYSIS_BREAKDOWNS.tm95_99}
                className={
                  !isValueSaved(ANALYSIS_BREAKDOWNS.tm95_99, tm95_99)
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={tm100}
                setFn={setTm100}
                dataTestid={ANALYSIS_BREAKDOWNS.tm100}
                className={
                  !isValueSaved(ANALYSIS_BREAKDOWNS.tm100, tm100)
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={public100}
                setFn={setPublic100}
                dataTestid={ANALYSIS_BREAKDOWNS.public100}
                className={
                  !isValueSaved(ANALYSIS_BREAKDOWNS.public100, public100)
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
            <td>
              <InputPercentage
                value={tm100InContext}
                setFn={setTm100InContext}
                dataTestid={ANALYSIS_BREAKDOWNS.tm100InContext}
                className={
                  !isValueSaved(
                    ANALYSIS_BREAKDOWNS.tm100InContext,
                    tm100InContext,
                  )
                    ? 'analysis-value-not-saved'
                    : ''
                }
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  )
}
