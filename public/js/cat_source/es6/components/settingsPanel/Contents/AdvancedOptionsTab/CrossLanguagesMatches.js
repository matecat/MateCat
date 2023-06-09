import React, {useEffect, useState} from 'react'
import {Select} from '../../../common/Select'
import SegmentActions from '../../../../actions/SegmentActions'

export const CrossLanguagesMatches = ({
  multiMatchLangs,
  setMultiMatchLangs,
}) => {
  const languages = config.languages_array.map((lang) => {
    return {name: lang.name, id: lang.code}
  })
  const [activeLang1, setActiveLang1] = useState(
    multiMatchLangs
      ? languages.find((lang) => lang.id === multiMatchLangs.primary)
      : undefined,
  )
  const [activeLang2, setActiveLang2] = useState(
    multiMatchLangs
      ? languages.find((lang) => lang.id === multiMatchLangs.secondary)
      : undefined,
  )

  useEffect(() => {
    UI.crossLanguageSettings = {
      primary: activeLang1?.id,
      secondary: activeLang2?.id,
    }
    localStorage.setItem(
      'multiMatchLangs',
      JSON.stringify(UI.crossLanguageSettings),
    )
    /*if (SegmentActions.getContribution) {
      if (primary) {
        SegmentActions.modifyTabVisibility('multiMatches', true)
        $('section.loaded').removeClass('loaded')
        SegmentActions.getContribution(UI.currentSegmentId, true)
      } else {
        SegmentActions.modifyTabVisibility('multiMatches', false)
        SegmentActions.activateTab(UI.currentSegmentId, 'matches')
        SegmentActions.updateAllSegments()
      }
    }*/
  }, [activeLang1, activeLang2])
  return (
    <div className="options-box multi-match">
      <h3>Cross-language Matches</h3>
      <p>
        Get translation suggestions in other target languages you know as
        reference.
      </p>
      <div className="multi-match-select-container">
        <Select
          name="multi-match-1"
          id="multi-match-1"
          title="Primary language suggestion"
          placeholder="Primary language suggestion"
          options={languages}
          activeOption={activeLang1}
          showSearchBar={true}
          onSelect={(lang) => setActiveLang1(lang)}
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
          onSelect={(lang) => setActiveLang2(lang)}
        />
      </div>
    </div>
  )
}
