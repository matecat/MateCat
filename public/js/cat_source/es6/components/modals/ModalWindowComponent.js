import React from 'react'

import ModalContainerComponent from './ModalContainerComponent'
import ModalOverlayComponent from './ModalOverlayComponent'

class ModalWindowComponent extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      isShowingModal: false,
      component: '',
      compProps: {
        overlay: false,
      },
      title: '',
      styleContainer: '',
      onCloseCallback: false,
    }
    this.showModalComponent = this.showModalComponent.bind(this)
  }

  onCloseModal() {
    if (this.state.compProps && this.state.compProps.onCloseCallback) {
      this.state.compProps.onCloseCallback()
    }
    this.setState({
      isShowingModal: false,
      component: '',
      compProps: {},
      title: '',
      styleContainer: '',
      onCloseCallback: false,
    })
  }

  showModalComponent(component, props, title, style, onCloseCallback) {
    this.setState({
      isShowingModal: true,
      component: component,
      compProps: props,
      title: title,
      styleContainer: style,
      onCloseCallback: onCloseCallback,
    })
  }

  allowHTML(string) {
    return {__html: string}
  }

  componentDidMount() {
    $(this.modalRef).focus()
  }

  render() {
    return (
      <div>
        {this.state.isShowingModal &&
        this.state.compProps &&
        this.state.compProps.overlay ? (
          <ModalOverlayComponent
            onClose={this.onCloseModal.bind(this)}
            ref={(modal) => (this.modalRef = modal)}
            title={this.state.title}
            styleContainer={this.state.styleContainer}
          >
            <this.state.component {...this.state.compProps} />
          </ModalOverlayComponent>
        ) : this.state.isShowingModal ? (
          <ModalContainerComponent
            onClose={this.onCloseModal.bind(this)}
            ref={(modal) => (this.modalRef = modal)}
            title={this.state.title}
            styleContainer={this.state.styleContainer}
          >
            <this.state.component {...this.state.compProps} />
          </ModalContainerComponent>
        ) : null}
      </div>
    )
  }
}

export default ModalWindowComponent
