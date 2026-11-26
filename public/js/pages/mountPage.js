import {createRoot} from 'react-dom/client'
import {rootErrorOptions} from '../utils/rootErrorOptions'
import {ApplicationWrapper} from '../components/common/ApplicationWrapper'
import React from 'react'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
import {ModalWindow} from '../components/modals/ModalWindow'
import {PageErrorBoundary} from '../components/common/PageErrorBoundary/PageErrorBoundary'

export const mountPage = ({Component, rootElement}) => {
  document.addEventListener('DOMContentLoaded', () => {
    const pageRoot = createRoot(rootElement, rootErrorOptions)

    const Page = () => {
      return (
        <PageErrorBoundary>
          <ApplicationWrapper>
            <Component />
            <ModalWindow />
          </ApplicationWrapper>
        </PageErrorBoundary>
      )
    }

    pageRoot.render(React.createElement(Page))

    const mountPointNotificationBox = document.getElementsByClassName(
      'notifications-wrapper',
    )[0]
    if (mountPointNotificationBox) {
      const notificationBoxRoot = createRoot(
        mountPointNotificationBox,
        rootErrorOptions,
      )
      notificationBoxRoot.render(<NotificationBox />)
    }
  })
}
