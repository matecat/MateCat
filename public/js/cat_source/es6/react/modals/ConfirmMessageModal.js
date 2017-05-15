
class ConfirmMessageModal extends React.Component {


    constructor(props) {
        super(props);
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        return <div className="message-modal">
            <div className="matecat-modal-middle">
                <div className="ui one column grid center aligned">
                    <div className="column" style={{fontSize:'18px'}}>
                        <p dangerouslySetInnerHTML={this.allowHTML(this.props.text)}/>
                    </div>
                    <div className="column">
                        {this.props.cancelCallback ? (
                            <div className="ui button-modal grey margin right-10" onClick={this.props.cancelCallback}>{this.props.cancelText}</div>
                        ) : ('') }
                        {this.props.warningCallback ? (
                                <div className="ui button-modal orange margin left-10 right-10" onClick={this.props.warningCallback}>{this.props.warningText}</div>
                            ) : ('') }
                        {this.props.successCallback ? (
                                <div className="ui button-modal blue margin left-10" onClick={this.props.successCallback}>{this.props.successText}</div>
                            ) : ('') }
                    </div>
                </div>
            </div>
        </div>;
    }
}
SuccessModal.propTypes = {
    text: React.PropTypes.string
};


export default ConfirmMessageModal ;
