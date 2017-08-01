let OutsourceInfo = require('./OutsourceInfo').default;
let Immutable = require('immutable');
let GMTSelect = require('./GMTSelect').default;


class OutsourceVendor extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            outsource: false,
            revision: true,
            chunkQuote: null,
            extendedView: this.props.extendedView,
            timezone: $.cookie( "matecat_timezone"),
            changeRates: $.parseJSON( $.cookie( "matecat_changeRates"))
        };
        this.getOutsourceQuote = this.getOutsourceQuote.bind(this);
        if ( config.enable_outsource ) {
            this.getOutsourceQuote();
        }
        this.getChangeRates();

        this.currencies = {
            "EUR" : { symbol :"€", name:'Euro (EUR)' },
            "USD" : { symbol :"US$", name:'US dollar (USD)' },
            "AUD" : { symbol :"$", name:'Australian dollar (AUD)' },
            "CAD" : { symbol :"$", name:'Canadian dollar (CAD)' },
            "NZD" : { symbol :"$", name:'New Zealand dollar (NZD)' },
            "GBP" : { symbol :"£", name:'Pound sterling (GBP)' },
            "BRL" : { symbol :"R$", name:'Real (BRL)' },
            "RUB" : { symbol :"руб", name:'Russian ruble (RUB)' },
            "SEK" : { symbol :"kr", name:'Swedish krona (SEK)' },
            "CHF" : { symbol :"Fr.", name:'Swiss franc (CHF)' },
            "TRY" : { symbol :"TL", name:'Turkish lira (TL)' },
            "KRW" : { symbol :"￦", name:'Won (KRW)' },
            "JPY" : { symbol :"￥", name:'Yen (JPY)' },
            "PLN" : { symbol :"zł", name:'Złoty (PLN)' }
        };
    }

    getOutsourceQuote(delivery) {
        let self = this;
        let typeOfService = this.state.revision ? "premium" : "professional";
        let fixedDelivery =  (delivery) ? delivery : "";
        let timezoneToShow = this.state.timezone;
        let currency = this.getCurrentCurrency();
        API.OUTSOURCE.getOutsourceQuote(this.props.project.get('id'), this.props.project.get('password'),
            this.props.job.get('id'), this.props.job.get('password'), fixedDelivery, typeOfService, timezoneToShow, currency)
            .done(function (quoteData) {
                if (quoteData.data) {

                    self.quoteResponse = quoteData.data[0];
                    let chunk = Immutable.fromJS(quoteData.data[0][0]);

                    self.url_ok = quoteData.return_url.url_ok;
                    self.url_ko = quoteData.return_url.url_ko;
                    self.confirm_urls = quoteData.return_url.confirm_urls;
                    self.data_key = chunk.get('id');

                    self.setState({
                        outsource: true,
                        chunkQuote: chunk
                    });

                    // Intercom
                    $(document).trigger('outsource-rendered', { quote_data : self.quoteResponse } );

            }
        });
    }

    getCurrentCurrency() {
        let currency = $.cookie( "matecat_currency");
        if (!_.isUndefined(currency)) {
            return currency;
        } else {
            $.cookie( "matecat_currency", 'EUR');
            return 'EUR';
        }
    }

    getPriceCurrencySymbol() {
        if (this.state.outsource) {
            let currency = this.state.chunkQuote.get('currency');
            return this.currencies[currency].symbol;
        } else {
            return "";
        }
    }

    getCurrencyPrice(price) {
        let current = this.getCurrentCurrency();
        return parseFloat(price * this.state.changeRates[current]/this.state.changeRates['EUR'])
            .toFixed(2);
    }

    changeTimezone(value) {
        $.cookie( "matecat_timezone" , value);
        this.setState({
            timezone: value
        });
    }

    getChangeRates() {
        let self = this;
        let changeRates = $.cookie( "matecat_changeRates");
        if( _.isUndefined(changeRates)) {
            API.OUTSOURCE.fetchChangeRates().done(function (response) {
                self.setState({
                    changeRates: response.data
                });
                $.cookie( "matecat_changeRates", $.parseJSON( response.data ) , { expires: 1 });
            });
        }
    }

    onCurrencyChange(value) {
        $.cookie("matecat_currency", value);
        let quote = this.state.chunkQuote.set('currency', value);
        this.setState({
            chunkQuote: quote
        });
    }


    sendOutsource() {

        this.quoteResponse[0] = this.state.chunkQuote;


        $(this.outsourceForm).find('input[name=url_ok]').attr('value', this.url_ok);
        $(this.outsourceForm).find('input[name=url_ko]').attr('value', this.url_ko);
        $(this.outsourceForm).find('input[name=confirm_urls]').attr('value', this.confirm_urls);
        $(this.outsourceForm).find('input[name=data_key]').attr('value', this.data_key);

        UI.populateOutsourceForm();

        //IMPORTANT post out the quotes
        $(this.outsourceForm).find('input[name=quoteData]').attr('value', JSON.stringify( this.quoteResponse ) );
        $(this.outsourceForm).submit();
        $(this.outsourceForm).find('input[name=quoteData]').attr('value', '' );
    }

    clickRevision() {
        let service = (this.revisionCheckbox.checked) ? 'premium' : 'professional';
        let quote = this.state.chunkQuote.set('typeOfService', service);
        this.setState({
            chunkQuote: quote,
            revision: this.revisionCheckbox.checked
        });

    }

    getDeliveryDate() {
        if (this.state.outsource) {
            // let timeZone = this.getTimeZone();
            // let dateString =  this.getDateString(deliveryToShow, timeZone);
            if (this.state.revision) {
                return APP.getGMTDate( this.state.chunkQuote.get('r_delivery'));
            } else {
                return APP.getGMTDate(  this.state.chunkQuote.get('delivery'));
            }
        }

    }

    getPrice() {
        let price;
        if (this.state.outsource) {
            if (this.state.revision) {
                price = parseFloat(parseFloat(   this.state.chunkQuote.get('r_price') ) + parseFloat(   this.state.chunkQuote.get('price') ));
            } else {
                price = parseFloat(this.state.chunkQuote.get('price'))
            }
            return this.getCurrencyPrice(parseFloat(price));
        }
    }

    getPricePW(price) {
        if (this.state.outsource) {
            let words = this.props.job.get('stats').get('TOTAL');
            return ( parseFloat(price) / words ).toFixed(3).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")
        }
    }

    getTranslatedWords() {
        if (this.state.outsource) {
            return   this.state.chunkQuote.get('t_words_total').toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")
        }
    }

    getTranslatorSubjects() {
        if (this.state.outsource) {
            if (  this.state.chunkQuote.get('t_chosen_subject').length > 0 &&   this.state.chunkQuote.get('t_subjects').length > 0) {
                return   this.state.chunkQuote.get('t_chosen_subject') + ', ' +   this.state.chunkQuote.get('t_subjects');
            } else if (  this.state.chunkQuote.get('t_chosen_subject').length > 0) {
                return   this.state.chunkQuote.get('t_chosen_subject');
            } else {
                return   this.state.chunkQuote.get('t_subjects');
            }
        }
    }

    viewMoreClick() {
        this.setState({
            extendedView: true
        });
    }

    getExtendedView() {
        let delivery = this.getDeliveryDate();
        let price = this.getPrice();
        let priceCurrencySymbol = this.getPriceCurrencySymbol();
        let translatedWords = this.getTranslatedWords();
        let translatorSubjects = this.getTranslatorSubjects();
        let pricePWord = this.getPricePW(price);
        return <div className="outsource-to-vendor sixteen wide column">
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
                        { this.state.chunkQuote.get('t_name') !== '' ? (
                        <div className="translator-details-box">

                            <div className="ui list left">
                                <div className="item">{this.state.chunkQuote.get('t_name')}<b> by Translated</b></div>
                                <div className="item"><b>{this.state.chunkQuote.get('t_experience_years')} years of experience</b></div>
                                <div className="item">
                                <div className="ui mini star rating" data-rating={Number(((parseFloat(this.state.chunkQuote.get('t_vote'))/2)/10).toFixed(0))} data-max-rating="5"
                                ref={(rating) => this.rating = rating}/></div>
                            </div>
                            <div className="ui list right">
                                <div className="item"><b>{translatedWords}</b> words translated last 12 months</div>
                                <div className="item"><b>{translatorSubjects}</b></div>
                             </div>
                        </div>
                        ) : (
                            <div className="translator-details-box">
                                <div className="translator-no-found">
                                    <p>
                                        Translated.net uses the <b>most qualified translator</b> for your subject (General) and <b>keeps using the same translator for your next projects. </b>
                                        Translated has over 15 years' experience as a translation company and offers <b><a href="#">two key guarantees on quality and delivery.</a></b>
                                    </p>
                                </div>
                            </div>
                        )}


                        <div className="job-details-box">
                            <div className="source-target-outsource st-details">
                                <div className="source-box">{this.props.job.get('sourceTxt')}</div>
                                <div className="in-to">
                                    <i className="icon-chevron-right icon" />
                                </div>
                                <div className="target-box">{this.props.job.get('targetTxt')}</div>
                            </div>
                            <div className="job-payment">
                                <div className="not-payable">{this.props.standardWC} words</div>
                                <div className="payable">{this.state.chunkQuote.get('words')} words</div>
                            </div>
                        </div>
                        <div className="job-price">
                            {priceCurrencySymbol} {this.getCurrencyPrice(this.state.chunkQuote.get('price')).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")}</div>
                    </div>
                    <div className="revision-box">
                        <div className="add-revision">
                            <div className="ui checkbox">
                                <input type="checkbox" defaultChecked
                                       ref={(checkbox) => this.revisionCheckbox = checkbox}
                                       onClick={this.clickRevision.bind(this)}/>
                                <label>Add Revision</label>
                            </div>
                        </div>
                        <div className="job-price">
                            {priceCurrencySymbol} {this.getCurrencyPrice(this.state.chunkQuote.get('r_price')).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")}</div>
                    </div>
                    <div className="delivery-order">
                        <div className="delivery-box">
                            <label>Delivery date:</label>
                            <div className="delivery-date">{delivery.day + ' ' + delivery.month}</div>
                            <span>at</span>
                            <div className="delivery-time">{delivery.time}</div>
                            <div className="gmt">
                                <GMTSelect changeValue={this.changeTimezone.bind(this)}/>
                            </div>
                            <div className="need-it-faster">
                                <a className="faster"
                                   ref={(faster) => this.dateFaster = faster}
                                >Need it faster?</a>
                            </div>
                        </div>
                    </div>
                    <div className="order-box-outsource">
                        <div className="order-box">
                            <div className="outsource-price">
                                {priceCurrencySymbol} {price.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")}
                            </div>
                            <div className="select-value">
                                {/*<a className="value">about €{pricePWord} / word</a>*/}
                                <h4 className="ui header">
                                    <div className="content">
                                        <div className="ui inline dropdown"
                                            ref={(select) => this.currencySelect = select}>
                                            <a className="price-pw">about {priceCurrencySymbol} {pricePWord} / word</a>
                                            <i className="dropdown icon"/>
                                            <div className="menu">
                                                <div className="header">Select Currency</div>
                                                <div className="divider"/>
                                                <div className="item" data-value="EUR" data-symbol="€">Euro (EUR)</div>
                                                <div className="item" data-value="USD" data-symbol="US$">US dollar (USD)</div>
                                                <div className="item" data-value="AUD" data-symbol="$">Australian dollar (AUD)</div>
                                                <div className="item" data-value="CAD" data-symbol="$">Canadian dollar (CAD)</div>
                                                <div className="item" data-value="NZD" data-symbol="$">New Zealand dollar (NZD)</div>
                                                <div className="item" data-value="GBP" data-symbol="£">Pound sterling (GBP)</div>
                                                <div className="item" data-value="BRL" data-symbol="R$">Real (BRL)</div>
                                                <div className="item" data-value="RUB" data-symbol="руб">Russian ruble (RUB)</div>
                                                <div className="item" data-value="SEK" data-symbol="kr">Swedish krona (SEK)</div>
                                                <div className="item" data-value="CHF" data-symbol="Fr.">Swiss franc (CHF)</div>
                                                <div className="item" data-value="TRY" data-symbol="TL">Turkish lira (TL)</div>
                                                <div className="item" data-value="KRW" data-symbol="￦">Won (KRW)</div>
                                                <div className="item" data-value="JPY" data-symbol="￥">Yen (JPY)</div>
                                                <div className="item" data-value="PLN" data-symbol="zł">Złoty (PLN)</div>
                                            </div>
                                        </div>
                                    </div>
                                </h4>
                            </div>
                        </div>
                        <div className="order-button-outsource">
                            <a className="open-order ui green button" onClick={this.sendOutsource.bind(this)}>Order now</a>
                        </div>
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
            <OutsourceInfo/></div>
    }

    getCompactView() {
        let delivery = this.getDeliveryDate();
        let price = this.getPrice();
        let priceCurrencySymbol = this.getPriceCurrencySymbol();
        let pricePWord = this.getPricePW(price);
        return <div className="outsource-to-vendor-reduced sixteen wide column">
            {this.state.outsource ? (
                <div className="container-reduced">
                    <div className="title-reduced">Let us do it for you</div>

                    <div className="payment-service">
                        <div className="service-box">
                            <div className="service project-management">Outsource: PM </div>
                            <div className="service translation"> + Translation </div>
                            <div className="service revision"> + Revision</div>
                        </div>
                        <div className="fiducial-logo">
                            <div className="translated-logo">Guaranteed by
                                <img className="logo-t" src="/public/img/logo_translated.png" />
                            </div>
                        </div>
                        <div className="view-more">
                            <a className="open-view-more"
                               onClick={this.viewMoreClick.bind(this)}>+ view more</a>
                        </div>
                    </div>
                    <div className="delivery-order">
                        <div className="delivery-box">
                            <label>Delivery date:</label><br />
                            <div className="delivery-date">{delivery.day + ' ' + delivery.month}</div>
                            <span>at</span>
                            <div className="delivery-time">{delivery.time}</div>
                            <div className="gmt">
                                <GMTSelect direction="up"/>
                            </div>
                        </div>
                    </div>
                    <div className="order-box-outsource">
                        <div className="order-box">
                            <div className="outsource-price">
                                {priceCurrencySymbol} {price.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,")}
                            </div>
                            <div className="select-value">
                                <h4 className="ui header">
                                    <div className="content">
                                        <div className="ui inline dropdown"
                                             ref={(select) => this.currencySelect = select}>
                                            <a className="price-pw">about {priceCurrencySymbol} {pricePWord} / word</a>
                                            <i className="dropdown icon"/>
                                            <div className="menu">
                                                <div className="header">Select Currency</div>
                                                <div className="divider"/>
                                                <div className="item" data-value="EUR" data-symbol="€">Euro (EUR)</div>
                                                <div className="item" data-value="USD" data-symbol="US$">US dollar (USD)</div>
                                                <div className="item" data-value="AUD" data-symbol="$">Australian dollar (AUD)</div>
                                                <div className="item" data-value="CAD" data-symbol="$">Canadian dollar (CAD)</div>
                                                <div className="item" data-value="NZD" data-symbol="$">New Zealand dollar (NZD)</div>
                                                <div className="item" data-value="GBP" data-symbol="£">Pound sterling (GBP)</div>
                                                <div className="item" data-value="BRL" data-symbol="R$">Real (BRL)</div>
                                                <div className="item" data-value="RUB" data-symbol="руб">Russian ruble (RUB)</div>
                                                <div className="item" data-value="SEK" data-symbol="kr">Swedish krona (SEK)</div>
                                                <div className="item" data-value="CHF" data-symbol="Fr.">Swiss franc (CHF)</div>
                                                <div className="item" data-value="TRY" data-symbol="TL">Turkish lira (TL)</div>
                                                <div className="item" data-value="KRW" data-symbol="￦">Won (KRW)</div>
                                                <div className="item" data-value="JPY" data-symbol="￥">Yen (JPY)</div>
                                                <div className="item" data-value="PLN" data-symbol="zł">Złoty (PLN)</div>
                                            </div>
                                        </div>
                                    </div>
                                </h4>
                            </div>
                        </div>
                        <div className="order-button-outsource">
                            <a className="open-order ui green button"
                               onClick={this.sendOutsource.bind(this)}>Order now</a>
                        </div>
                    </div>
                </div>
            ):(
                <div className="ui active inverted dimmer">
                    <div className="ui medium text loader">Loading</div>
                </div>
            )}
        </div>
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount () {}

    componentWillUnmount() {
        $(this.dateFaster).datetimepicker('destroy');
    }

    componentDidUpdate() {
        let self = this;
        if (this.state.outsource ) {
            $(this.dateFaster).datetimepicker({
                validateOnBlur: false,
                defaultTime: '09:00',
                minDate:0,
                showApplyButton: true,
                closeOnTimeSelect:false,
                selectButtonLabel: "Get Price",
                allowTimes: ['07:00', '09:00', '11:00', '13:00', '15:00', '17:00', '19:00', '21:00'],
                disabledWeekDays: [0,6],
                onSelectDateButton: function (newDateTime) {
                    let timestamp = (new Date(newDateTime)).getTime();
                    self.setState({
                        outsource: false
                    });
                    self.getOutsourceQuote(timestamp);
                },
                onChangeDateTime: function (newDateTime, $input) {
                    console.log("onChangeDateTime");
                }
            });

            let currencyToShow = $.cookie( "matecat_currency" );
            $(this.currencySelect).dropdown('set selected', currencyToShow);
            $(this.currencySelect).dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.onCurrencyChange(value)
                }
            });
        }
        $(this.rating).rating('disable');
    }

    shouldComponentUpdate(nextProps, nextState){
        return (!nextState.chunkQuote.equals(this.state.chunkQuote)
        || nextState.outsource !== this.state.outsource
        || nextState.extendedView !== this.state.extendedView
        || nextState.revision !== this.state.revision
        || nextState.timezone !== this.state.timezone);
    }

    render() {

        return <div className="background-outsource-vendor">
            {this.state.extendedView ? ( this.getExtendedView()
            ): (
                this.getCompactView()
            )}

            <form id="continueForm" action={config.outsource_service_login} method="POST" target="_blank"
                  ref={(form) => this.outsourceForm = form}>
                <input type="hidden" name="url_ok" value=""/>
                <input type="hidden" name="url_ko" value=""/>
                <input type="hidden" name="confirm_urls" value=""/>
                <input type='hidden' name='data_key' value="" />
                <input type="hidden" name="quoteData" value=""/>
            </form>
        </div>;

    }
}

export default OutsourceVendor ;