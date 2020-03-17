import classnames from "classnames";

class RevisionFeedbackModal extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            sending: false
        }
    }

    sendFeedback() {
        this.setState({
            sending: true
        });
        CatToolActions.sendRevisionFeedback(this.textarea.value).done(() => APP.ModalWindow.onCloseModal());
    }

    render() {

        return <div className="shortcuts-modal">
            <div className="matecat-modal-top">
                <h1>Leave a feedback</h1>
            </div>
            <div className="matecat-modal-middle">
                <span>Please leave some feedback for the translator on the job quality</span>
                <textarea style={{width: "100%", height: "100px"}} placeholder="Write here" ref={(textarea)=>this.textarea=textarea}/>
            </div>
            <div className="matecat-modal-bottom">
                <div className="ui one column grid right aligned">
                    <div className="column">
                        { this.state.sending ? (
                            <div className="ui button cancel-button disabled">
                                <span className="button-loader show" style={{left: "280px"}}/>
                                Submit</div>
                        ) : (
                            <div className="ui button cancel-button" onClick={()=>this.sendFeedback()}>Submit</div>
                        ) }

                        <div className="create-team ui primary button open " onClick={()=>APP.ModalWindow.onCloseModal()}>I'll do it later</div>
                    </div>
                </div>
            </div>
        </div>
    }
}


export default RevisionFeedbackModal ;