import React, {useContext, useEffect, useRef, useState} from 'react'
import {Select} from '../../../common/Select'
import SegmentActions from '../../../../actions/SegmentActions'
import ApplicationStore from '../../../../stores/ApplicationStore'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import {isEqual} from 'lodash'
import SegmentStore from '../../../../stores/SegmentStore'
import {METADATA_KEY} from '../../../../constants/Constants'

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

  const isFirstRender = useRef(true)

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
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }

    const settings = {
      primary: activeLang1?.id,
      secondary: activeLang2?.id,
    }

    setMultiMatchLangs(
      typeof activeLang1?.id !== 'undefined' ||
        typeof activeLang2?.id !== 'undefined'
        ? settings
        : undefined,
    )
    setUserMetadataKey(
      METADATA_KEY,
      typeof activeLang1?.id !== 'undefined' ||
        typeof activeLang2?.id !== 'undefined'
        ? settings
        : {},
    )

    if (SegmentActions.getContribution) {
      if (settings.primary || settings.secondary) {
        SegmentActions.modifyTabVisibility('multiMatches', true)
        SegmentActions.getContributions(
          SegmentStore.getCurrentSegmentId(),
          settings,
          true,
        )
      } else {
        SegmentActions.modifyTabVisibility('multiMatches', false)
        SegmentActions.updateAllSegments()
      }
    }
  }, [activeLang1, activeLang2, setUserMetadataKey])

  return (
    <div className="options-box multi-match">
      <div className="option-description">
        <h3>Reference languages</h3>
        <p>
          View additional matches in a dedicated tab in up to two languages of
          your choice.
        </p>
      </div>
      <div
        className="options-select-container"
        data-testid="container-crosslanguagesmatches"
      >
        <Select
          name="multi-match-1"
          id="multi-match-1"
          label="Language 1"
          title="Primary language suggestion"
          placeholder="None"
          options={languages}
          activeOption={activeLang1}
          showSearchBar={true}
          maxHeightDroplist={280}
          showResetButton={true}
          resetFunction={() => setActiveLang1()}
          onSelect={(option) => {
            const lang = !(activeLang1 && activeLang1.id === option.id)
              ? option
              : undefined
            setActiveLang1(lang)
          }}
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
          name="multi-match-2"
          id="multi-match-2"
          label="Language 2"
          title="Secondary language suggestion"
          placeholder="None"
          options={languages}
          activeOption={activeLang2}
          showSearchBar={true}
          maxHeightDroplist={280}
          showResetButton={true}
          resetFunction={() => setActiveLang2()}
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
