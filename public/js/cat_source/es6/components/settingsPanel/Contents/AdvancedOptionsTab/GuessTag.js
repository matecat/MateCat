import Switch from '../../../common/Switch'
import React, {useState} from 'react'
import PropTypes from 'prop-types'

export const GuessTag = ({setGuessTagActive = () => {}}) => {
  const [active, setActive] = useState(false)
  const disabled = false

  return (
    <div className="options-box tagp">
      {/*TODO Check tag porojection active, check tm.html show_tag_projection*/}
      <h3>Guess tag position</h3>
      <p>
        <span className="option-tagp-languages">
          Not available for:
          <span className="option-notsupported-languages"></span>.
          <br />
        </span>
        <span className="option-tagp-revise">
          Not available in revise mode.
          <br />
        </span>
        Enable this functionality to let Matecat automatically place the tags
        where they belong.
        <a
          className="tooltip-guess-tags"
          href="https://site.matecat.com/support/translating-projects/guess-tag-position/#gtp_lang"
          target="_blank"
        >
          Supported languages
        </a>
      </p>
      <Switch onChange={active} active={active} disabled={disabled} />
    </div>
  )
}
GuessTag.propTypes = {
  setGuessTagActive: PropTypes.func,
}
