let OutsourceConstants = require('../../constants/OutsourceConstants');
let GMTSelect = require('./GMTSelect').default;
class AssignToTranslator extends React.Component {


    constructor(props) {
        super(props);
        this.timezone = APP.getDefaultTimeZone();
    }

    shareJob() {
        //Check email and validations errors
        let date = $(this.date).data('timestamp');
        let email = this.email.value;

        OutsourceActions.sendJobToTranslator(email, date, this.timezone, this.props.job.toJS(), this.props.project);
    }

    GmtSelectChanged(value) {
        this.timezone = value;
        console.log("GMT Changed : ", value);
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount () {
        $('#date-picker-translator').datetimepicker({
            step:30,
            validateOnBlur: false,
            defaultTime: '09:00',
            // format:'unixtime',
            onChangeDateTime: function (newDateTime, $input) {
                let date = APP.fromDateToString(newDateTime);
                let dateString = date.day + ' ' + date.month + ' ' + date.year + ' at ' + date.time ;
                $input.val(dateString);
                $input.data('timestamp', new Date(newDateTime).getTime());
            }
            // allowTimes: ['07:00', '09:00', '13:00', '15:00', '17:00', '19:00', '21:00'],
            // onGenerate: function(ct) {
            //     $(this).find('.xdsoft_date.xdsoft_weekend').addClass('disabled');
            //     $('.xdsoft_calendar table thead tr th').filter(':nth-child(6), :nth-child(7)').addClass('disabled');
            // }
        });
    }

    componentWillUnmount() {
        $('#date-picker-translator').datetimepicker('destroy');
    }

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
            <div className="title-url ui grid">
                <div className="title four wide column">
                    Assign Job to translator
                </div>
                <div className="job-url twelve wide column">
                    <a href={window.location.protocol + '//' + window.location.host + this.props.url} target="_blank">
                        {window.location.protocol + '//' + window.location.host + this.props.url}</a>
                </div>
                <div className="left">

                </div>
                <div className="translator-assignee">
                    <div className="ui form">
                        <div className="fields">
                            <div className="field">
                                <label>Translator email</label>
                                <input type="email" placeholder="translator@email.com" defaultValue={translatorEmail}
                                       ref={(email) => this.email = email}/>
                            </div>
                            <div className="field">
                                <label>Middle name</label>
                                <input id="date-picker-translator" type="text" placeholder="Date" defaultValue={date}
                                       ref={(date) => this.date = date}/>
                            </div>
                            <div className="field gmt">
                                <GMTSelect changeValue={this.GmtSelectChanged.bind(this)}/>
                                {/*<div className="ui button">*/}
                                    {/*(GMT +2)*/}
                                {/*</div>*/}
                            </div>
                        </div>
                    </div>
                </div>
                <div className="send-job-box">
                    <div className="send-job ui primary button"
                    onClick={this.shareJob.bind(this)}>Send Job to Translator</div>
                </div>

            </div>
        </div>;
    }
}

export default AssignToTranslator ;