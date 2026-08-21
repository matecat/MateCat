import React, {useState, useRef, useCallback, useEffect} from 'react'

import {ModalContainer} from './ModalContainer'
import {ModalOverlay} from './ModalOverlay'
import ModalsConstants from '../../constants/ModalsConstants'
import CatToolStore from '../../stores/CatToolStore'
import {resolveModal} from './modalRegistry'

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

export const ModalWindow = () => {
  const [modalState, setModalState] = useState(initialState)

  const modalStateRef = useRef(modalState)
  modalStateRef.current = modalState

  const onCloseModal = useCallback(() => {
    modalStateRef.current.compProps?.onCloseCallback?.()

    setModalState(initialState)
  }, [])

  /**
   * @NOTE DO NOT REMOVE THIS FUNCTION!
   *
   * It is currently used from outside of the React tree
   * for legacy reasons, so before removing we need
   * to refactor these dirty usages first!
   */
  const showModalComponent = useCallback(
    (
      component,
      props = {},
      title,
      style,
      onCloseCallback,
      showHeader,
      styleBody,
      isCloseButtonDisabled,
    ) => {
      const resolvedComponent = resolveModal(component)
      setModalState({
        ...initialState,
        title,
        component: resolvedComponent,
        showHeader,
        compProps: {
          ...initialState.compProps,
          ...props,
          onClose: onCloseModal,
          closeOnSuccess: props?.closeOnSuccess ? props.closeOnSuccess : true,
        },
        styleContainer: style,
        onCloseCallback: onCloseCallback,
        isShowingModal: true,
        styleBody,
        isCloseButtonDisabled: isCloseButtonDisabled,
      })
    },
    [],
  )

  useEffect(() => {
    CatToolStore.addListener(ModalsConstants.SHOW_MODAL, showModalComponent)
    CatToolStore.addListener(ModalsConstants.CLOSE_MODAL, onCloseModal)

    componentStatus.isMounted = true

    return () => {
      CatToolStore.removeListener(
        ModalsConstants.SHOW_MODAL,
        showModalComponent,
      )
      CatToolStore.removeListener(ModalsConstants.CLOSE_MODAL, onCloseModal)

      componentStatus.isMounted = false
    }
  }, [])

  const {
    component: InjectedComponent,
    title,
    styleContainer,
    compProps,
    isShowingModal,
    showHeader,
    styleBody,
    isCloseButtonDisabled,
  } = modalState

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
              onClose: onCloseModal,
              closeOnOutsideClick: compProps.closeOnOutsideClick,
              styleBody,
              isCloseButtonDisabled,
            },
            <InjectedComponent {...compProps} />,
          )}
    </div>
  )
}
