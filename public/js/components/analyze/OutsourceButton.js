import React, {createRef, useRef} from 'react'
import {ANALYSIS_STATUS} from '../../constants/Constants'
import TranslatedIcon from '../../../img/icons/TranslatedIcon'
import Tooltip from '../common/Tooltip'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'

const OutsourceButton = ({chunk, index, openOutsourceModal, status}) => {
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
        <Button
          ref={createRef()}
          mode={BUTTON_MODE.GHOST}
          size={BUTTON_SIZE.SMALL}
          disabled={true}
          style={{fontWeight: 500}}
        >
          <TranslatedIcon size={16} />
          <div>Buy Translation</div>
        </Button>
      </Tooltip>
    </div>
  ) : (
    <Button
      mode={BUTTON_MODE.GHOST}
      size={BUTTON_SIZE.SMALL}
      disabled={status !== ANALYSIS_STATUS.DONE}
      onClick={openOutsourceModal(index, chunk)}
      style={{fontWeight: 500}}
    >
      <TranslatedIcon size={16} />
      <div>Buy Translation</div>
    </Button>
  )
}

export default OutsourceButton
