class ModalComponent extends React.Component {


    constructor(props) {
        super(props);
    }

    closeModal(event){
        event.stopPropagation();
        if ($(event.target).closest('.matecat-modal-content').length == 0 || $(event.target).hasClass('close-matecat-modal')) {
            this.props.onClose();
        }
    }

    componentWillMount() {

    }

    componentDidMount() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        return <div id="matecat-modal" className="matecat-modal" onClick={(e) => this.closeModal.call(this,e)}>
                <div className="matecat-modal-content" style={this.props.styleContainer}>
                    <div className="matecat-modal-header">
                        <span className="close-matecat-modal" onClick={this.closeModal.bind(this)}>×</span>
                        <h2>{this.props.title}</h2>
                    </div>
                    <div className="matecat-modal-body">
                        {this.props.children}
                    </div>
                    <div className="matecat-modal-footer">

                    </div>
                </div>

            </div>
    }
}

ModalComponent.propTypes = {
    onClose: React.PropTypes.func,
    title: React.PropTypes.string
};
//
// ModalComponent.defaultProps = {
//
// };

export default ModalComponent ;
