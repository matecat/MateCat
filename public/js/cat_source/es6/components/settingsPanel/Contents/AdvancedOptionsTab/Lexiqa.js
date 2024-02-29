import React, {useEffect, useState} from 'react'
import Switch from '../../../common/Switch'
import LXQ from '../../../../utils/lxq.main'
import ApplicationStore from '../../../../stores/ApplicationStore'

export const checkLexiqaIsEnabled = ({
  sourceLang,
  targetLangs,
  acceptedLanguages = config.lexiqa_languages,
  license = config.lxq_license,
}) => {
  let notAcceptedLanguages = []
  const targetLanguages = targetLangs
  const sourceAccepted = acceptedLanguages.indexOf(sourceLang.code) > -1
  const targetAccepted =
    targetLanguages.filter(function (n) {
      if (acceptedLanguages.indexOf(n.code) === -1) {
        notAcceptedLanguages.push(
          ApplicationStore.getLanguageNameFromLocale(n.code),
        )
      }
      return acceptedLanguages.indexOf(n.code) != -1
    }).length > 0

  if (!sourceAccepted) {
    notAcceptedLanguages.push(sourceLang.name)
  }
  //disable LexiQA
  const disableLexiQA = !(sourceAccepted && targetAccepted && license)

  return {disableLexiQA, notAcceptedLanguages}
}

export const Lexiqa = ({
  lexiqaActive,
  setLexiqaActive,
  sourceLang,
  targetLangs,
}) => {
  const [disabled, setDisable] = useState(false)
  const [notSupportedLangs, setNotSupportedLangs] = useState([])
  const lexiqaLicense = config.lxq_license

  const onChange = (selected) => {
    setLexiqaActive(selected)
    if (config.is_cattool) {
      if (selected) {
        LXQ.enable()
      } else {
        LXQ.disable()
      }
    }
  }
  useEffect(() => {
    const {disableLexiQA, notAcceptedLanguages} = checkLexiqaIsEnabled({
      sourceLang,
      targetLangs,
    })

    if (notAcceptedLanguages.length > 0) {
      setNotSupportedLangs(notAcceptedLanguages)
    }
    if (disableLexiQA) {
      setDisable(true)
      setLexiqaActive(false)
    }
  }, [sourceLang, targetLangs, setLexiqaActive])

  return (
    <div className="options-box qa-box">
      {/*  Lexiqa
    TODO: check lexiqa licence active
  */}
      <div className="option-description">
        <h3>
          QA by <img src="/public/img/lexiqa-new-2.png" />
        </h3>
        <p>
          {!lexiqaLicense && (
            <span className="option-qa-box-languages">
              Request your license key at{' '}
              <a href="https://www.lexiqa.net">https://www.lexiqa.net</a>
              <br />
            </span>
          )}
          {notSupportedLangs.length > 0 && lexiqaLicense && (
            <span className="option-qa-box-languages">
              Not available for:
              <span className="option-notsupported-languages">
                {notSupportedLangs.join(', ')}
              </span>
              .
              <br />
            </span>
          )}
          Linguistic QA with automated checks for punctuation, numerals, links,
          symbols, etc.
          <a
            className="tooltip-options"
            href="https://guides.matecat.com/matecat-qa-with-lexiqa?hs_preview=ZjhRGTNW-10067295048"
            target="_blank"
          >
            Supported languages
          </a>
        </p>
      </div>
      <div className="options-box-value">
        <Switch
          active={lexiqaActive}
          onChange={onChange}
          disabled={disabled}
          testId="switch-lexiqa"
        />
      </div>
    </div>
  )
}
