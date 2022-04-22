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
  },
  title: '',
  styleContainer: '',
  onCloseCallback: false,
}

export class ModalWindowComponent extends React.Component {
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
  showModalComponent = (component, props, title, style, onCloseCallback) => {
    this.setState({
      title,
      component,
      compProps: {
        ...props,
        onClose: this.onCloseModal,
        closeOnSuccess: props.closeOnSuccess ? props.closeOnSuccess : true,
      },
      styleContainer: style,
      onCloseCallback: onCloseCallback,
      isShowingModal: true,
    })
  }

  componentDidMount() {
    CatToolStore.addListener(
      ModalsConstants.SHOW_MODAL,
      this.showModalComponent,
    )
    CatToolStore.addListener(ModalsConstants.CLOSE_MODAL, this.onCloseModal)
  }

  componentWillUnmount() {
    CatToolStore.removeListener(
      ModalsConstants.SHOW_MODAL,
      this.showModalComponent,
    )
    CatToolStore.removeListener(ModalsConstants.CLOSE_MODAL, this.onCloseModal)
  }

  render() {
    const {
      component: InjectedComponent,
      title,
      styleContainer,
      compProps,
      isShowingModal,
    } = this.state

    return (
      <div>
        {!isShowingModal
          ? null
          : React.createElement(
              compProps?.overlay ? ModalOverlay : ModalContainer,
              {
                title,
                styleContainer,
                onClose: this.onCloseModal,
              },
              <InjectedComponent {...compProps} />,
            )}
      </div>
    )
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const mountPoint = createRoot(document.getElementById('modal'))
  mountPoint.render(React.createElement(ModalWindowComponent, {}))
})
