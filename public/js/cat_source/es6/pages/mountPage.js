import {createRoot} from 'react-dom/client'
import {DataLoader} from '../components/common/DataLoader'
import React from 'react'
import NotificationBox from '../components/notificationsComponent/NotificationBox'

export const mountPage = ({Component, rootElement}) => {
  document.addEventListener('DOMContentLoaded', () => {
    const pageRoot = createRoot(rootElement)

    const Page = () => {
      return (
        <DataLoader>
          <Component />
        </DataLoader>
      )
    }

    pageRoot.render(React.createElement(Page))

    const mountPointNotificationBox = document.getElementsByClassName(
      'notifications-wrapper',
    )[0]
    const notificationBoxRoot = createRoot(mountPointNotificationBox)
    notificationBoxRoot.render(<NotificationBox />)
  })
}
