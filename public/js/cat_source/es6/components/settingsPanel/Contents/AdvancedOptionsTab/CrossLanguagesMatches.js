import React, {useEffect, useState} from 'react'
import {Select} from '../../../common/Select'
import SegmentActions from '../../../../actions/SegmentActions'
import ApplicationStore from '../../../../stores/ApplicationStore'

export const CrossLanguagesMatches = ({
  multiMatchLangs,
  setMultiMatchLangs,
}) => {
  const languages = ApplicationStore.getLanguages().map((lang) => {
    return {name: lang.name, id: lang.code}
  })
  const [activeLang1, setActiveLang1] = useState(
    languages.find((lang) => lang.id === multiMatchLangs?.primary),
  )
  const [activeLang2, setActiveLang2] = useState(
    languages.find((lang) => lang.id === multiMatchLangs?.secondary),
  )

  useEffect(() => {
    const languages = ApplicationStore.getLanguages().map((lang) => {
      return {name: lang.name, id: lang.code}
    })

    setActiveLang1(
      languages.find((lang) => lang.id === multiMatchLangs?.primary),
    )

    setActiveLang2(
      languages.find((lang) => lang.id === multiMatchLangs?.secondary),
    )
  }, [multiMatchLangs?.primary, multiMatchLangs?.secondary])

  useEffect(() => {
    const settings = {
      primary: activeLang1?.id,
      secondary: activeLang2?.id,
    }

    setMultiMatchLangs(
      typeof activeLang1?.id !== 'undefined' ? settings : undefined,
    )
    localStorage.setItem('multiMatchLangs', JSON.stringify(settings))
    if (SegmentActions.getContribution && config.is_cattool) {
      if (settings.primary) {
        SegmentActions.modifyTabVisibility('multiMatches', true)
        SegmentActions.getContribution(UI.currentSegmentId, settings, true)
      } else {
        SegmentActions.modifyTabVisibility('multiMatches', false)
        SegmentActions.activateTab(UI.currentSegmentId, 'matches')
        SegmentActions.updateAllSegments()
      }
    }
  }, [activeLang1, activeLang2, setMultiMatchLangs])

  useEffect(() => {
    if (!activeLang1) {
      setActiveLang2()
    }
  }, [activeLang1])
  return (
    <div className="options-box multi-match">
      <div className="option-description">
        <h3>Cross-language Matches</h3>
        <p>
          Get translation suggestions in other target languages you know as
          reference.
        </p>
      </div>
      <div
        className="options-select-container"
        data-testid="container-crosslanguagesmatches"
      >
        <Select
          name="multi-match-1"
          id="multi-match-1"
          title="Primary language suggestion"
          placeholder="Primary language suggestion"
          options={languages}
          activeOption={activeLang1}
          showSearchBar={true}
          onToggleOption={(option) => {
            if (activeLang1 && activeLang1.id === option.id) {
              setActiveLang1()
            } else {
              setActiveLang1(option)
            }
          }}
          multipleSelect="dropdown"
        />
        <Select
          name="multi-match-1"
          id="multi-match-1"
          title="Secondary language suggestion"
          placeholder="Secondary language suggestion"
          options={languages}
          activeOption={activeLang2}
          showSearchBar={true}
          isDisabled={!!!activeLang1}
          onToggleOption={(option) => {
            if (activeLang2 && activeLang2.id === option.id) {
              setActiveLang2()
            } else {
              setActiveLang2(option)
            }
          }}
          multipleSelect="dropdown"
        />
      </div>
    </div>
  )
}
