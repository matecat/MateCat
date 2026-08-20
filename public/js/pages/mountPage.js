// Every page bootstraps through here, so this is what makes the extension points
// exist. Importing it per entry instead would leave a point defined on some pages
// and unknown on others, which is a runtime throw rather than a missing feature.
import '../extensions/extensionManifest'
import {createRoot} from 'react-dom/client'
import {ApplicationWrapper} from '../components/common/ApplicationWrapper'
import React from 'react'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
import {ModalWindow} from '../components/modals/ModalWindow'

export const mountPage = ({Component, rootElement}) => {
  document.addEventListener('DOMContentLoaded', () => {
    const pageRoot = createRoot(rootElement)

    const Page = () => {
      return (
        <ApplicationWrapper>
          <Component />
          <ModalWindow />
        </ApplicationWrapper>
      )
    }

    pageRoot.render(React.createElement(Page))

    const mountPointNotificationBox = document.getElementsByClassName(
      'notifications-wrapper',
    )[0]
    if (mountPointNotificationBox) {
      const notificationBoxRoot = createRoot(mountPointNotificationBox)
      notificationBoxRoot.render(<NotificationBox />)
    }
  })
}
