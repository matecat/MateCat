
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
                <div className="ui one column grid left aligned">
                    <div className="column">
                        <p dangerouslySetInnerHTML={this.allowHTML(this.props.text)}/>
                    </div>
                    <div className="column">
                        <div className="ui button grey" onClick={this.props.cancelCallback}>{this.props.cancelText}</div>
                        <div className="ui button blue" onClick={this.props.successCallback}>{this.props.successText}</div>
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
