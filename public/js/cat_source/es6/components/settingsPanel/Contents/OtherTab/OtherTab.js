import React, {useCallback, useContext, useMemo} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {CharacterCounter} from './CharacterCounter'
import {AiAssistant} from './AiAssistant'
import {Team} from './Team'
import {SpacePlaceholder} from './SpacePlaceholder'

export const OtherTab = () => {
  const {user, modifyingCurrentTemplate, currentProjectTemplate} =
    useContext(SettingsPanelContext)

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
    <div className="other-options-box settings-panel-contentwrapper-tab-background">
      {config.is_cattool && <SpacePlaceholder />}

      {config.is_cattool && <CharacterCounter />}

      {config.is_cattool && config.isOpenAiEnabled && <AiAssistant />}

      {config.isLoggedIn === 1 && !config.is_cattool && (
        <Team {...{selectedTeam, setSelectedTeam}} />
      )}
    </div>
  )
}
OtherTab.propTypes = {}
