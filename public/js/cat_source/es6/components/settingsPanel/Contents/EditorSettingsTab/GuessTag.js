import Switch from '../../../common/Switch'
import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentActions from '../../../../actions/SegmentActions'

export const checkGuessTagIsEnabled = ({
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
    isEnabled:
      arrayIntersection.length > 0 && config.defaults?.tag_projection === 1,
    arrayIntersection,
    notSupportedCouples,
  }
}

export const GuessTag = ({
  guessTagActive,
  setGuessTagActive,
  sourceLang,
  targetLangs,
}) => {
  const [disabled, setDisable] = useState(!!config.isReview)
  const [notSupportedLangs, setNotSupportedLangs] = useState([])
  const onChange = (selected) => {
    setGuessTagActive(selected)
    if (config.is_cattool) {
      SegmentActions.changeTagProjectionStatus(selected)
    }
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
      setGuessTagActive(false)
      setDisable(true)
    }
  }, [sourceLang, targetLangs, setGuessTagActive])
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
          active={guessTagActive}
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
