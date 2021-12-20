import React from 'react'
import ReactDOM from 'react-dom'

import {ModalContainer} from './ModalContainer'
import {ModalOverlay} from './ModalOverlay'

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
      compProps: props,
      styleContainer: style,
      onCloseCallback: onCloseCallback,
      isShowingModal: true,
    })
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
export let ModalWindow

document.addEventListener('DOMContentLoaded', () => {
  const mountPoint = document.getElementById('modal')
  ModalWindow = ReactDOM.render(
    React.createElement(ModalWindowComponent, {}),
    mountPoint,
  )
})
