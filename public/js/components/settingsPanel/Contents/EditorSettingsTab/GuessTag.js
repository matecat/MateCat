import Switch from '../../../common/Switch'
import React, {useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'

const checkGuessTagIsEnabled = ({
  sourceLang,
  targetLangs,
  acceptedLanguages = config.tag_projection_languages,
}) => {
  const sourceLanguageCode = sourceLang.code
  const sourceLanguageText = sourceLang.name
  let languageCombinations = []
  let notSupportedCouples = []

  targetLangs.forEach(function (target) {
    var elem = {}
    elem.targetCode = target.code
    elem.sourceCode = sourceLanguageCode
    elem.targetName = target.name
    elem.sourceName = sourceLanguageText
    languageCombinations.push(elem)
  })
  //Intersection between the combination of choosen languages and the supported
  const arrayIntersection = languageCombinations.filter(function (n) {
    const elemST = n.sourceCode.split('-')[0] + '-' + n.targetCode.split('-')[0]
    const elemTS = n.targetCode.split('-')[0] + '-' + n.sourceCode.split('-')[0]
    if (
      typeof acceptedLanguages[elemST] == 'undefined' &&
      typeof acceptedLanguages[elemTS] == 'undefined'
    ) {
      notSupportedCouples.push(n.sourceName + ' - ' + n.targetName)
    }
    return (
      typeof acceptedLanguages[elemST] !== 'undefined' ||
      typeof acceptedLanguages[elemTS] !== 'undefined'
    )
  })

  return {
    arrayIntersection,
    notSupportedCouples,
  }
}

const METADATA_KEY = 'guess_tags'

export const GuessTag = ({sourceLang, targetLangs}) => {
  const {userInfo, setUserMetadataKey} = useContext(ApplicationWrapperContext)

  const [isActive, setIsActive] = useState(
    userInfo.metadata[METADATA_KEY] === 1,
  )
  const [disabled, setDisable] = useState(!!config.isReview)
  const [notSupportedLangs, setNotSupportedLangs] = useState([])

  const onChange = (isActive) => {
    setIsActive(isActive)

    setUserMetadataKey(METADATA_KEY, isActive ? 1 : 0).then(() =>
      SegmentActions.changeTagProjectionStatus(isActive),
    )
  }

  useEffect(() => {
    const {arrayIntersection, notSupportedCouples} = checkGuessTagIsEnabled({
      targetLangs,
      sourceLang,
    })

    if (notSupportedCouples.length > 0) {
      setNotSupportedLangs(notSupportedCouples)
    }

    //disable Tag Projection
    if (arrayIntersection.length == 0) {
      setIsActive(false)
      setDisable(true)
    }
  }, [sourceLang, targetLangs])

  return (
    <div className="options-box tagp">
      {/*TODO Check tag porojection active, check tm.html show_tag_projection*/}
      <div className="option-description">
        <h3>Guess tag position</h3>
        <p>
          {notSupportedLangs.length > 0 && (
            <span className="option-tagp-languages">
              Not available for:
              <span className="option-notsupported-languages">
                {notSupportedLangs.join(', ')}
              </span>
              .
              <br />
            </span>
          )}
          {config.isReview && (
            <span className="option-tagp-revise">
              Not available in revise mode.
              <br />
            </span>
          )}
          Enable this functionality to let Matecat automatically place the tags
          where they belong.
          <a
            className="tooltip-options"
            href="https://guides.matecat.com/guess-tag-position"
            target="_blank"
          >
            Supported languages
          </a>
        </p>
      </div>
      <div className="options-box-value">
        <Switch
          onChange={onChange}
          active={isActive}
          disabled={disabled}
          testId="switch-guesstag"
        />
      </div>
    </div>
  )
}
GuessTag.propTypes = {
  setGuessTagActive: PropTypes.func,
  guessTagActive: PropTypes.bool,
  sourceLang: PropTypes.object,
  targetLangs: PropTypes.array,
}
