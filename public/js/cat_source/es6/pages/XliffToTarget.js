import React, {useContext} from 'react'
import usePortal from '../hooks/usePortal'
import Header from '../components/header/Header'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper'
import Footer from '../components/footer/Footer'
import SseListener from '../sse/SseListener'
import {mountPage} from './mountPage'
import {UploadXliff} from '../components/xliffToTarget/UploadXliff'

const headerMountPoint = document.querySelector('header.upload-page-header')

export const XliffToTarget = () => {
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const HeaderPortal = usePortal(headerMountPoint)

  return (
    <>
      <HeaderPortal>
        <Header
          showModals={false}
          showLinks={true}
          loggedUser={isUserLogged}
          user={isUserLogged ? userInfo.user : undefined}
        />
      </HeaderPortal>
      <div className="wrapper-xliff-to-target">
        <UploadXliff />
      </div>
      <Footer />
      <SseListener
        isAuthenticated={isUserLogged}
        userId={isUserLogged ? userInfo.user.uid : null}
      />
    </>
  )
}

mountPage({
  Component: XliffToTarget,
  rootElement: document.getElementsByClassName('xliff_to_target__page')[0],
})
