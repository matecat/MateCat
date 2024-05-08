import {Button, BUTTON_TYPE} from '../Button/Button'
import React from 'react'

export const UserDisconnectedBox = () => {
  return (
    <div className="user-disconnect-box">
      <div className="user-disconnect-box_content">
        <div>
          <h2>Please Sign in again</h2>
          <p>
            You were signed out of your account. Please press 'Reload' to sign
            in to Matecat again.
          </p>
          <Button
            type={BUTTON_TYPE.PRIMARY}
            onClick={() => window.location.reload()}
          >
            Reload
          </Button>
        </div>
      </div>
    </div>
  )
}
