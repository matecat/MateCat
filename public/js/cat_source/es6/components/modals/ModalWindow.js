import React from 'react'
import {createRoot} from 'react-dom/client'

import {ModalContainer} from './ModalContainer'
import {ModalOverlay} from './ModalOverlay'
import ModalsConstants from '../../constants/ModalsConstants'
import CatToolStore from '../../stores/CatToolStore'

const initialState = {
  isShowingModal: false,
  component: '',
  compProps: {
    overlay: false,
    closeOnOutsideClick: true,
  },
  title: '',
  styleContainer: '',
  onCloseCallback: false,
  showHeader: true,
  styleBody: '',
}

const componentStatus = (() => {
  const obj = {}
  let _isMounted = false
  let _resolve

  Object.defineProperty(obj, 'isMounted', {
    get: () => _isMounted,
    set: (value) => {
      _isMounted = value
      if (_isMounted && _resolve) _resolve()
    },
  })
  Object.defineProperty(obj, 'resolve', {
    enumerable: false,
    set: (value) => {
      _resolve = value
      if (_isMounted) _resolve()
    },
  })
  return obj
})()

export const onModalWindowMounted = () =>
  new Promise((resolve) => (componentStatus.resolve = resolve))

export class ModalWindow extends React.Component {
  state = initialState

  onCloseModal = () => {
    this.state.compProps?.onCloseCallback?.()

    this.setState(initialState)
  }

  /**
   * @NOTE DO NOT REMOVE THIS FUNCTION!
   *
   * It is currently used from outside of the React tree
   * for legacy reasons, so before removing we need
   * to refactor these dirty usages first!
   */
  showModalComponent = (
    component,
    props = {},
    title,
    style,
    onCloseCallback,
    showHeader,
    styleBody,
    isCloseButtonDisabled,
  ) => {
    this.setState({
      ...initialState,
      title,
      component,
      showHeader,
      compProps: {
        ...initialState.compProps,
        ...props,
        onClose: this.onCloseModal,
        closeOnSuccess: props?.closeOnSuccess ? props.closeOnSuccess : true,
      },
      styleContainer: style,
      onCloseCallback: onCloseCallback,
      isShowingModal: true,
      styleBody,
      isCloseButtonDisabled: isCloseButtonDisabled,
    })
  }

  componentDidMount() {
    CatToolStore.addListener(
      ModalsConstants.SHOW_MODAL,
      this.showModalComponent,
    )
    CatToolStore.addListener(ModalsConstants.CLOSE_MODAL, this.onCloseModal)

    componentStatus.isMounted = true
  }

  componentWillUnmount() {
    CatToolStore.removeListener(
      ModalsConstants.SHOW_MODAL,
      this.showModalComponent,
    )
    CatToolStore.removeListener(ModalsConstants.CLOSE_MODAL, this.onCloseModal)

    componentStatus.isMounted = false
  }

  render() {
    const {
      component: InjectedComponent,
      title,
      styleContainer,
      compProps,
      isShowingModal,
      showHeader,
      styleBody,
      isCloseButtonDisabled,
    } = this.state

    return (
      <div>
        {!isShowingModal
          ? null
          : React.createElement(
              compProps?.overlay ? ModalOverlay : ModalContainer,
              {
                title,
                showHeader,
                styleContainer,
                onClose: this.onCloseModal,
                closeOnOutsideClick: compProps.closeOnOutsideClick,
                styleBody,
                isCloseButtonDisabled,
              },
              <InjectedComponent {...compProps} />,
            )}
      </div>
    )
  }
}
