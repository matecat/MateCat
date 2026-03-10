import React, {useContext} from 'react'
import usePortal from '../hooks/usePortal'
import Header from '../components/header/Header'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import Footer from '../components/footer/Footer'
import SocketListener from '../sse/SocketListener'
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
      <div className="xliff-to-target-wrapper">
        <div className="wrapper-claim">
          <div className="wrapper-claim-content">
            <h1>XLIFF to Target File Conversion Tool</h1>
          </div>
        </div>

        <div className="wrapper-upload">
          {typeof isUserLogged !== 'undefined' && <UploadXliff />}
        </div>
      </div>
      <Footer />
      <SocketListener
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
