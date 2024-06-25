import React, {useCallback, useContext, useMemo} from 'react'
import {SpeechToText} from './SpeechToText'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {GuessTag, checkGuessTagIsEnabled} from './GuessTag'
import {Lexiqa, checkLexiqaIsEnabled} from './Lexiqa'
import {CrossLanguagesMatches} from './CrossLanguagesMatches'
import {CharacterCounter} from './CharacterCounter'
import {AiAssistant} from './AiAssistant'
import {SegmentationRule} from './SegmentationRule'
import {Team} from './Team'
import {SpacePlaceholder} from './SpacePlaceholder'

export const AdvancedOptionsTab = () => {
  const {
    user,
    modifyingCurrentTemplate,
    currentProjectTemplate,
    sourceLang,
    targetLangs,
  } = useContext(SettingsPanelContext)

  // Speech to text
  const speechToTextActive = currentProjectTemplate.speech2text
  const setSpeechToTextActive = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      speech2text: value,
    }))

  // Guess tag
  const isGuessTagEnabled =
    checkGuessTagIsEnabled({sourceLang, targetLangs}).arrayIntersection.length >
    0
  const guessTagActive =
    isGuessTagEnabled && currentProjectTemplate.tagProjection
  const setGuessTagActive = useCallback(
    (value) =>
      isGuessTagEnabled &&
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        tagProjection: value,
      })),
    [isGuessTagEnabled, modifyingCurrentTemplate],
  )

  // Lexiqa
  const isLexiqaEnabled = !checkLexiqaIsEnabled({sourceLang, targetLangs})
    .disableLexiQA
  const lexiqaActive = isLexiqaEnabled && currentProjectTemplate.lexica
  const setLexiqaActive = useCallback(
    (value) =>
      isLexiqaEnabled &&
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        lexica: value,
      })),
    [isLexiqaEnabled, modifyingCurrentTemplate],
  )

  // Cross language matches
  const multiMatchLangs = currentProjectTemplate.crossLanguageMatches
  const setMultiMatchLangs = useCallback(
    (value) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        crossLanguageMatches: typeof value !== 'undefined' ? value : null,
      })),
    [modifyingCurrentTemplate],
  )

  // Segmentation rule
  const segmentationRule = currentProjectTemplate.segmentationRule
  const setSegmentationRule = useCallback(
    (value) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        segmentationRule: value,
      })),
    [modifyingCurrentTemplate],
  )

  const selectedTeam = useMemo(() => {
    const team =
      user?.teams.find(({id}) => id === currentProjectTemplate?.idTeam) ?? {}

    return {...team, id: team.id?.toString()}
  }, [user?.teams, currentProjectTemplate?.idTeam])
  const setSelectedTeam = useCallback(
    ({id}) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        idTeam: parseInt(id),
      })),
    [modifyingCurrentTemplate],
  )

  return (
    <div className="advanced-options-box settings-panel-contentwrapper-tab-background">
      <SpeechToText
        setSpeechToTextActive={setSpeechToTextActive}
        speechToTextActive={speechToTextActive}
      />

      {config.is_cattool && <SpacePlaceholder />}

      {config.show_tag_projection && (
        <GuessTag
          setGuessTagActive={setGuessTagActive}
          guessTagActive={guessTagActive}
          sourceLang={sourceLang}
          targetLangs={targetLangs}
        />
      )}

      <Lexiqa
        lexiqaActive={lexiqaActive}
        setLexiqaActive={setLexiqaActive}
        sourceLang={sourceLang}
        targetLangs={targetLangs}
      />

      {config.is_cattool && <CharacterCounter />}

      {config.is_cattool && config.isOpenAiEnabled && <AiAssistant />}

      <CrossLanguagesMatches
        multiMatchLangs={multiMatchLangs}
        setMultiMatchLangs={setMultiMatchLangs}
      />

      <SegmentationRule
        segmentationRule={segmentationRule}
        setSegmentationRule={setSegmentationRule}
      />
      {config.isLoggedIn === 1 && !config.is_cattool && (
        <Team {...{selectedTeam, setSelectedTeam}} />
      )}
    </div>
  )
}
AdvancedOptionsTab.propTypes = {}
