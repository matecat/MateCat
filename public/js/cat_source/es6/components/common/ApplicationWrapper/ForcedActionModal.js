import {Button, BUTTON_TYPE} from '../Button/Button'
import React from 'react'
export const FORCE_ACTIONS = {
  DISCONNECT: 'disconnect',
  RELOAD: 'reload',
}
export const ForcedActionModal = ({action = FORCE_ACTIONS.RELOAD}) => {
  return (
    <div className="user-disconnect-box">
      <div className="user-disconnect-box_content">
        {action === FORCE_ACTIONS.DISCONNECT ? (
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
        ) : null}

        {action === FORCE_ACTIONS.RELOAD ? (
          <div>
            <h2>Update Required</h2>
            <p>
              An important update has been released to improve the app's
              performance and security. To continue using the app, please
              refresh the page.
            </p>
            <p>
              Click <strong>Refresh</strong> or press <strong>Ctrl+R</strong>{' '}
              (Windows) / <strong>Cmd+R</strong> (Mac) to apply the latest
              changes.{' '}
            </p>
            <p>Thank you for your cooperation!</p>
            <Button
              type={BUTTON_TYPE.PRIMARY}
              onClick={() => window.location.reload()}
            >
              Refresh page
            </Button>
          </div>
        ) : null}
      </div>
    </div>
  )
}
