import PropTypes  from 'prop-types';


class ModalComponent extends React.Component {


    constructor(props) {
        super(props);
    }

    closeModal(event) {
        event.stopPropagation();
        if ($(event.target).closest('.matecat-modal-content').length == 0 || $(event.target).hasClass('close-matecat-modal')) {
            this.props.onClose();
        }
    }

    componentDidMount() {
        document.activeElement.blur()
    }

    componentWillUnmount() {
    }


    allowHTML(string) {
        return {__html: string};
    }

    render() {
        return <div id="matecat-modal-overlay" className="matecat-modal-overlay" onClick={(e) => this.closeModal.call(this, e)}>
            <div className="matecat-modal-content" style={this.props.styleContainer}>
                <div className="matecat-modal-header">
                    <div className="modal-logo"/>
                    <div>
                        <h2>{this.props.title}</h2>
                    </div>
                    <div>
                        <span className="close-matecat-modal x-popup" onClick={this.closeModal.bind(this)}/>
                    </div>
                </div>
                <div className="matecat-modal-body">
                    {this.props.children}
                </div>
            </div>
        </div>
    }
}

ModalComponent.propTypes = {
    onClose: PropTypes.func,
    title: PropTypes.string
};
//
// ModalComponent.defaultProps = {
//
// };

export default ModalComponent;
