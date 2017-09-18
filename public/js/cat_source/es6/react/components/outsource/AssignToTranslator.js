let OutsourceConstants = require('../../constants/OutsourceConstants');
let GMTSelect = require('./GMTSelect').default;
class AssignToTranslator extends React.Component {


    constructor(props) {
        super(props);
        this.timezone = APP.getDefaultTimeZone();
    }

    shareJob() {
        //Check email and validations errors
        let date = $(this.dateInput).data('timestamp');
        let email = this.email.value;

        OutsourceActions.sendJobToTranslator(email, date, this.timezone, this.props.job.toJS(), this.props.project.toJS());
        this.props.closeOutsource();
    }

    GmtSelectChanged(value) {
        this.timezone = value;
        console.log("GMT Changed : ", value);
    }

    checkSendToTranslatorButton() {
        // if ($(this.email).hasClass('error')) {
        //     $(this.email).removeClass('error');
        // }
        if (this.email.value.length > 0 && this.dateInput.value.length > 0 && APP.checkEmail(this.email.value)) {
            $(this.sendButton).removeClass('disabled');
            return true;
        } else {
            $(this.sendButton).addClass('disabled');
        }
    }

    componentDidMount () {
        $(this.dateInput).calendar({
            type: 'date'
        });
        $(this.dropdownTime).dropdown();
    }

    componentWillUnmount() {}

    componentDidUpdate() {}

    render() {
        let date = '';
        let translatorEmail = '';
        let delivery = '';
        if (this.props.job.get('translator')) {
            let delivery =  APP.fromDateToString(this.props.job.get('translator').get('delivery_timestamp') * 1000);
            date =  delivery.day + ' ' + delivery.month + ' ' + delivery.year + ' at ' + delivery.time;
            translatorEmail = this.props.job.get('translator').get('email');
        }
        return <div className="assign-job-translator sixteen wide column">
                    <div className="title">
                        Assign Job to translator
                    </div>
                    <div className="title-url">
                        <div className="translator-assignee">
                            <div className="ui form">
                                <div className="fields">
                                    <div className="field translator-email">
                                        <label>Translator email</label>
                                        <input type="email" placeholder="translator@email.com" defaultValue={translatorEmail}
                                               ref={(email) => this.email = email}
                                                onKeyUp={this.checkSendToTranslatorButton.bind(this)}/>
                                    </div>
                                    <div className="field translator-delivery ">
                                        <label>Delivery date</label>
                                        <div className="ui calendar" ref={(date) => this.dateInput = date}>
                                            <div className="ui input">
                                                <input type="text" placeholder="Date"
                                                       onChange={this.checkSendToTranslatorButton.bind(this)}/>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="field translator-time">
                                        <label>Time</label>
                                        <select className="ui dropdown"
                                        ref={(dropdown)=> this.dropdownTime = dropdown}>
                                            <option value="7">7:00 AM</option>
                                            <option value="9">9:00 AM</option>
                                            <option value="11">11:00 AM</option>
                                            <option value="13">1:00 PM</option>
                                            <option value="15">3:00 PM</option>
                                            <option value="17">5:00 PM</option>
                                            <option value="19">7:00 PM</option>
                                            <option value="21">9:00 PM</option>
                                        </select>
                                    </div>
                                    <div className="field gmt">
                                        <GMTSelect changeValue={this.GmtSelectChanged.bind(this)}/>
                                    </div>
                                    <div className="field send-job-box">
                                        <button className="send-job ui primary button disabled"
                                        onClick={this.shareJob.bind(this)}
                                        ref={(send) => this.sendButton=send }>Send Job to Translator</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>;
    }
}

export default AssignToTranslator ;