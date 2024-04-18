import {Button, BUTTON_TYPE} from '../Button/Button'
import React from 'react'

export const UserDisconnectedBox = () => {
  return (
    <div className="user-disconnect-box">
      <div className="user-disconnect-box_content">
        <div>
          User disconnected
          <Button
            type={BUTTON_TYPE.PRIMARY}
            onClick={() => window.location.reload()}
          >
            Refresh
          </Button>
        </div>
      </div>
    </div>
  )
}
