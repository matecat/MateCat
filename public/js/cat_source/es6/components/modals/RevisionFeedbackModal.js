import classnames from "classnames";

class RevisionFeedbackModal extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            sending: false,
            feedback: this.props.feedback
        }
    }

    sendFeedback() {
        this.setState({
            sending: true
        });
        CatToolActions.sendRevisionFeedback(this.state.feedback).done(() => {
            UI.reloadQualityReport();
            APP.ModalWindow.onCloseModal();
            var notification = {
                title: 'Feedback sent',
                text: "Feedback has been sent correctly",
                type: 'success'
            };
            APP.addNotification(notification);
        }).fail(()=> {
            var notification = {
                title: 'Feedback not sent',
                text: "An error occurred while sending feedback please try again or contact support.",
                type: 'error'
            };
            APP.addNotification(notification);
        });
    }

    onChange = (e) => {
        let value = e.target.value;
        this.setState({
            feedback: value
        });
    }

    render() {
        let sendLabel = ( this.props.feedback ) ? "Modify" : "Submit";
        return <div className="shortcuts-modal">
            <div className="matecat-modal-top">
                <h1>Leave a feedback</h1>
            </div>
            <div className="matecat-modal-middle">
                <div className="matecat-modal-text">
                    <span>Please leave some feedback for the translator on the job quality</span>
                </div>
                <div className="matecat-modal-textarea">
                    <textarea value={this.state.feedback} style={{width: "100%", height: "100px"}} placeholder="Write here" onChange={this.onChange}/>
                </div>
            </div>
            <div className="matecat-modal-bottom">
                <div className="ui one column grid right aligned">
                    <div className="column">
                        { this.state.sending ? (
                            <div className="ui button cancel-button disabled">
                                <span className="button-loader show" style={{left: "280px"}}/>
                                {sendLabel}
                            </div>
                        ) : (
                            <div className="ui button cancel-button" onClick={()=>this.sendFeedback()}>
                                {sendLabel}
                            </div>
                        ) }

                        <div className="create-team ui primary button open " onClick={()=>APP.ModalWindow.onCloseModal()}>
                            {( this.props.feedback ) ? 'Close' : "I'll do it later"}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    }
}


export default RevisionFeedbackModal ;