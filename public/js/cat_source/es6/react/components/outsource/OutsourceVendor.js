let OutsourceInfo = require('./OutsourceInfo').default;
let Immutable = require('immutable');

class OutsourceVendor extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            outsource: false,
            revision: false,
            quoteResponse: null
        };
        this.getOutsourceQuote = this.getOutsourceQuote.bind(this);
        if ( config.enable_outsource ) {
            this.getOutsourceQuote();
        }
        this.datePickerStart = false;

    }

    getOutsourceQuote() {
        let self = this;
        let typeOfService = this.state.revision ? "premium" : "professional";
        let fixedDelivery =  $( "#forceDeliveryChosenDate" ).text();
        UI.getOutsourceQuoteFromManage(this.props.project.get('id'), this.props.project.get('password'), this.props.job.get('id'), this.props.job.get('password'), fixedDelivery, typeOfService).done(function (quoteData) {
            if (quoteData.data) {

                this.state.quoteResponse = Immutable.fromJS(quoteData.data[0]);
                self.chunk = quoteData.data[0][0];

                UI.url_ok = quoteData.return_url.url_ok;
                UI.url_ko = quoteData.return_url.url_ko;
                UI.confirm_urls = quoteData.return_url.confirm_urls;
                UI.data_key = self.chunk.id;

                self.setState({
                    outsource: true
                });
            }
        });
    }

    clickRevision() {
        this.setState({
            revision: this.revisionCheckbox.checked
        });
    }

    getDeliveryDate() {
        if (this.state.outsource) {
            // let timeZone = this.getTimeZone();
            // let dateString =  this.getDateString(deliveryToShow, timeZone);
            if (this.state.revision) {
                return APP.getGMTDate(this.chunk.r_delivery);
            } else {
                return APP.getGMTDate(this.chunk.delivery);
            }
        }

    }

    getPrice() {
        if (this.state.outsource) {
            if (this.state.revision) {
                return parseFloat(parseFloat( this.chunk.r_price ) + parseFloat( this.chunk.price )).toFixed( 2);
            } else {
                return this.chunk.price;
            }
        }
    }

    getTranslatedWords() {
        if (this.state.outsource) {
            return this.chunk.t_words_total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")
        }
    }

    getTranslatorSubjects() {
        if (this.state.outsource) {
            if (this.chunk.t_chosen_subject.length > 0 && this.chunk.t_subjects.length > 0) {
                return this.chunk.t_chosen_subject + ', ' + this.chunk.t_subjects;
            } else if (this.chunk.t_chosen_subject.length > 0) {
                return this.chunk.t_chosen_subject;
            } else {
                return this.chunk.t_subjects;
            }
        }
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount () {

    }

    componentWillUnmount() {
        $(this.dateFaster).datetimepicker('destroy');
    }

    componentDidUpdate() {
        if (this.state.outsource && !this.datePickerStart) {
            this.datePickerStart = true;
            $(this.dateFaster).datetimepicker({
                validateOnBlur: false,
                defaultTime: '09:00',
                minDate:0,
                showApplyButton: true,
                closeOnTimeSelect:false,
                selectButtonLabel: "Get Price",
                allowTimes: ['07:00', '09:00', '11:00', '13:00', '15:00', '17:00', '19:00', '21:00'],
                disabledWeekDays: [0,6],
                onSelectDateButton: function () {

                },
                onChangeDateTime: function (newDateTime, $input) {
                    console.log("onChangeDateTime");
                }
            });
        }
    }

    shouldComponentUpdate(nextProps, nextState){

    }

    render() {
        let delivery = this.getDeliveryDate();
        let price = this.getPrice();
        let translatedWords = this.getTranslatedWords();
        let translatorSubjects = this.getTranslatorSubjects();
        return <div className="outsource-to-translated sixteen wide column">
            <div className="payment-service">
                <div className="service-box">
                    <div className="service project-management">Outsource: Project Management </div>
                    <div className="service translation"> + Translation </div>
                    {this.state.revision ? (<div className="service revision"> + Revision</div>) : (null)}
                </div>
                <div className="fiducial-logo">
                    <div className="translated-logo">Guaranteed by
                        <img className="logo-t" src="/public/img/logo_translated.png" />
                    </div>
                </div>
            </div>
            {(this.state.outsource ? (
            <div className="payment-details-box shadow-1">

                <div className="translator-job-details">
                    <div className="translator-details-box">
                        <div className="ui list left">
                            <div className="item">{this.chunk.t_name}<b> by Translated</b></div>
                            <div className="item"><b>{this.chunk.t_experience_years} years of experience</b></div>
                            <div className="item">
                                <div className="ui mini star rating" data-rating="4" data-max-rating="5" />
                            </div>
                        </div>
                        <div className="ui list right">
                            <div className="item"><b>{translatedWords}</b> words translated last 12 months</div>
                            <div className="item"><b>{translatorSubjects}</b></div>
                        </div>
                    </div>
                    <div className="job-details-box">
                        <div className="source-target-outsource st-details">
                            <div className="source-box">{this.props.job.get('sourceTxt')}</div>
                            <div className="in-to">
                                <i className="icon-chevron-right icon" />
                            </div>
                            <div className="target-box">{this.props.job.get('targetTxt')}</div>
                        </div>
                        <div className="job-payment">
                            <div className="not-payable">{this.props.job.get('total_raw_wc')} words</div>
                            <div className="payable">{this.props.job.get('stats').get('TOTAL_FORMATTED')} words</div>
                        </div>
                    </div>
                    <div className="job-price">€{this.chunk.price}</div>
                </div>
                <div className="revision-box">
                    <div className="add-revision">
                        <div className="ui checkbox">
                            <input type="checkbox"
                            ref={(checkbox) => this.revisionCheckbox = checkbox}
                            onClick={this.clickRevision.bind(this)}/>
                            <label>Add Revision</label>
                        </div>
                    </div>
                    <div className="job-price">€{this.chunk.r_price}</div>
                </div>
                <div className="delivery-order">
                    <div className="delivery-box">
                        <label>Delivery date:</label>
                        <div className="delivery-date">{delivery.day + ' ' + delivery.month}</div>
                        <span>at</span>
                        <div className="delivery-time">{delivery.time}</div>
                        <div className="gmt-button">
                            <div className="ui button">
                                (GMT +2)
                            </div>
                        </div>
                        <div className="need-it-faster">
                            <a className="faster"
                               ref={(faster) => this.dateFaster = faster}
                            >Need it faster?</a>
                        </div>
                    </div>
                </div>
                <div className="order-box-outsource">
                    <div className="outsource-price">
                        €{price}
                    </div>
                    <div className="select-value">
                        <a className="value">about €0.96 / word</a>
                    </div>
                </div>
                <div className="order-button-outsource">
                    <a className="open-order ui green button">Order now</a>
                </div>
            </div>
            ) : (
                <div className="payment-details-box shadow-1">
                    <div className="ui active inverted dimmer">
                        <div className="ui medium text loader">Loading</div>
                    </div>
                </div>
            ))}
            <div className="easy-pay-box">
                <h4 className="easy-pay">Easy payments</h4>
                <p>Pay a single monthly invoice within 30 days of receipt</p>
            </div>
            <OutsourceInfo/>
        </div>;
    }
}

export default OutsourceVendor ;