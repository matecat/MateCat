
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
                <div className="ui one column grid ">
                    <div className="column left aligned" style={{fontSize:'18px'}}>
                        <p dangerouslySetInnerHTML={this.allowHTML(this.props.text)}/>
                    </div>
                    <div className="column right aligned">
                        {this.props.cancelCallback ? (
                            <div className="ui button cancel-button" onClick={this.props.cancelCallback}>{this.props.cancelText}</div>
                        ) : ('') }
                        {this.props.warningCallback ? (
                                <div className="ui button-modal orange margin left-10 right-10" onClick={this.props.warningCallback}>{this.props.warningText}</div>
                            ) : ('') }
                        {this.props.successCallback ? (
                                <div className="ui primary button right floated" style={{fontSize: "18px", padding: "10px 20px"}} onClick={this.props.successCallback}>{this.props.successText}</div>
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
