import React, {useContext, useEffect, useState} from 'react'
import {Select} from '../../../common/Select'
import SegmentActions from '../../../../actions/SegmentActions'
import ApplicationStore from '../../../../stores/ApplicationStore'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper'
import {isEqual} from 'lodash'

const METADATA_KEY = 'cross_language_matches'

export const CrossLanguagesMatches = () => {
  const {userInfo, setUserMetadataKey} = useContext(ApplicationWrapperContext)

  const [multiMatchLangs, setMultiMatchLangs] = useState(
    userInfo.metadata[METADATA_KEY],
  )

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

    const newValueActiveLang1 = languages.find(
      (lang) => lang.id === multiMatchLangs?.primary,
    )
    setActiveLang1((prevState) =>
      !isEqual(prevState, newValueActiveLang1)
        ? newValueActiveLang1
        : prevState,
    )

    const newValueActiveLang2 = languages.find(
      (lang) => lang.id === multiMatchLangs?.secondary,
    )
    setActiveLang2((prevState) =>
      !isEqual(prevState, newValueActiveLang2)
        ? newValueActiveLang2
        : prevState,
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
    setUserMetadataKey(
      METADATA_KEY,
      typeof activeLang1?.id !== 'undefined' ? settings : {},
    )

    if (SegmentActions.getContribution) {
      if (settings.primary) {
        SegmentActions.modifyTabVisibility('multiMatches', true)
        SegmentActions.getContribution(UI.currentSegmentId, settings, true)
      } else {
        SegmentActions.modifyTabVisibility('multiMatches', false)
        SegmentActions.updateAllSegments()
      }
    }
  }, [activeLang1, activeLang2, setUserMetadataKey])

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
          onSelect={(option) =>
            setActiveLang1(
              !(activeLang1 && activeLang1.id === option.id)
                ? option
                : undefined,
            )
          }
        >
          {({name, id}) => ({
            row: (
              <div className="language-dropdown-item-container">
                <div className="code-badge">
                  <span>{id}</span>
                </div>

                <span>{name}</span>
              </div>
            ),
          })}
        </Select>
        <Select
          name="multi-match-1"
          id="multi-match-1"
          title="Secondary language suggestion"
          placeholder="Secondary language suggestion"
          options={languages}
          activeOption={activeLang2}
          showSearchBar={true}
          isDisabled={!activeLang1}
          onSelect={(option) =>
            setActiveLang2(
              !(activeLang2 && activeLang2.id === option.id)
                ? option
                : undefined,
            )
          }
        >
          {({name, id}) => ({
            row: (
              <div className="language-dropdown-item-container">
                <div className="code-badge">
                  <span>{id}</span>
                </div>

                <span>{name}</span>
              </div>
            ),
          })}
        </Select>
      </div>
    </div>
  )
}
