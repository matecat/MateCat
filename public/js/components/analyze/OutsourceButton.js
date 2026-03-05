import React, {useRef} from 'react'
import {ANALYSIS_STATUS} from '../../constants/Constants'
import TranslatedIcon from '../../../img/icons/TranslatedIcon'
import Tooltip from '../common/Tooltip'

const OutsourceButton = ({chunk, index, openOutsourceModal, status}) => {
  const outsourceButton = useRef()
  return !chunk.outsource_available &&
    chunk.outsource_info?.custom_payable_rate ? (
    <div
      className={'outsource-translation outsource-translation-disabled'}
      id="open-quote-request"
    >
      <Tooltip
        content={
          <div>
            Jobs created with custom billing models cannot be outsourced to
            Translated.
            <br />
            In order to outsource this job to Translated, please recreate it
            using Matecat&apos;s standard billing model
          </div>
        }
      >
        <div ref={outsourceButton}>
          <a>Buy Translation</a>
          <span>
            from <TranslatedIcon />
          </span>
        </div>
      </Tooltip>
    </div>
  ) : (
    <div
      className={`outsource-translation  ${
        status !== ANALYSIS_STATUS.DONE ? 'outsource-translation-disabled' : ''
      }`}
      onClick={openOutsourceModal(index, chunk)}
      id="open-quote-request"
    >
      <a>Buy Translation</a>
      <span>
        from <TranslatedIcon />
      </span>
    </div>
  )
}

export default OutsourceButton
