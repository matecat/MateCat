export default class GMTSelect extends React.Component {

    constructor(props) {
        super(props);
    }
    
    componentDidMount()  {
        let self = this;
        let direction = 'downward';
        if (this.props.direction && this.props.direction === 'up') {
            direction = 'upward';
        }
        var timezoneToShow = $.cookie( "matecat_timezone" );
        $(this.gmtSelect).dropdown('set selected', timezoneToShow);
        $(this.gmtSelect).dropdown({
            direction: direction,
            onChange: function(value, text, $selectedItem) {
                if (self.props.changeValue) {
                    self.props.changeValue(value);
                }
            }
        });
    }

    render() {
        return <div className="ui selection floating dropdown gmt-select"
                ref={(gmtSelect) => this.gmtSelect = gmtSelect}>
                <input type="hidden" name="gmt" />
                <i className="dropdown icon" />
                <div className="default text">Select GMT</div>
                <div className="menu">
                    <div className="item" data-value="-11">
                        <div className="gmt-value">(GMT -11:00 )</div>
                        <div className="gmt-description"> Midway Islands, American Samoa</div> </div>
                    <div className="item" data-value="-10">
                        <div className="gmt-value">(GMT -10:00 )</div>
                        <div className="gmt-description"> Hawaii, Tahiti, Cook Islands</div> </div>
                    <div className="item" data-value="-9">
                        <div className="gmt-value">(GMT -9:00 )</div>
                        <div className="gmt-description"> Alaska</div> </div>
                    <div className="item" data-value="-8">
                        <div className="gmt-value">(GMT -8:00 )</div>
                        <div className="gmt-description"> Pacific Standard Time (LA, Vancouver)</div> </div>
                    <div className="item" data-value="-7">
                        <div className="gmt-value">(GMT -7:00 )</div>
                        <div className="gmt-description"> Mountain Standard Time (Denver, SLC)</div> </div>
                    <div className="item" data-value="-6">
                        <div className="gmt-value">(GMT -6:00 )</div>
                        <div className="gmt-description"> Central Standard Time (Mexico, Chicago)</div> </div>
                    <div className="item" data-value="-5">
                        <div className="gmt-value">(GMT -5:00 )</div>
                        <div className="gmt-description"> Eastern Standard Time (NYC, Toronto)</div> </div>
                    <div className="item" data-value="-4">
                        <div className="gmt-value">(GMT -4:00 )</div>
                        <div className="gmt-description"> Atlantic Standard Time (Santiago)</div> </div>
                    <div className="item" data-value="-4.5">
                        <div className="gmt-value">(GMT -4:30 )</div>
                        <div className="gmt-description"> Venezuela (Caracas)</div> </div>
                    <div className="item" data-value="-3">
                        <div className="gmt-value">(GMT -3:00 )</div>
                        <div className="gmt-description"> Brasília, São Paulo, Buenos Aires</div> </div>
                    <div className="item" data-value="-2">
                        <div className="gmt-value">(GMT -2:00 )</div>
                        <div className="gmt-description"> South Sandwich Islands</div> </div>
                    <div className="item" data-value="-1">
                        <div className="gmt-value">(GMT -1:00 )</div>
                        <div className="gmt-description"> Azores, Cape Verde (Praia)</div> </div>
                    <div className="item" data-value="0">
                        <div className="gmt-value">(GMT)</div>
                        <div className="gmt-description"> Western European Time (London, Lisbon)</div> </div>
                    <div className="item" data-value="1">
                        <div className="gmt-value">(GMT +1:00 )</div>
                        <div className="gmt-description"> Central European Time (Rome, Paris)</div> </div>
                    <div className="item" data-value="2">
                        <div className="gmt-value">(GMT +2:00 )</div>
                        <div className="gmt-description"> Eastern European Time, CAT </div> </div>
                    <div className="item" data-value="3">
                        <div className="gmt-value">(GMT +3:00 )</div>
                        <div className="gmt-description"> Arabia Standard Time (Baghdad, Riyadh)</div> </div>
                    <div className="item" data-value="3.5">
                        <div className="gmt-value">(GMT +3:30 )</div>
                        <div className="gmt-description"> Iran Standard Time (Tehran)</div> </div>
                    <div className="item" data-value="4">
                        <div className="gmt-value">(GMT +4:00 )</div>
                        <div className="gmt-description"> Moscow, St. Petersburg, Dubai</div> </div>
                    <div className="item" data-value="4.5">
                        <div className="gmt-value">(GMT +4:30 )</div>
                        <div className="gmt-description"> Afghanistan Time (Kabul)</div> </div>
                    <div className="item" data-value="5">
                        <div className="gmt-value">(GMT +5:00 )</div>
                        <div className="gmt-description"> Karachi, Tashkent, Maldive Islands</div> </div>
                    <div className="item" data-value="5.5">
                        <div className="gmt-value">(GMT +5:30 )</div>
                        <div className="gmt-description"> India Standard Time (Mumbai, Colombo)</div> </div>
                    <div className="item" data-value="6">
                        <div className="gmt-value">(GMT +6:00 )</div>
                        <div className="gmt-description"> Yekaterinburg, Almaty, Dhaka</div> </div>
                    <div className="item" data-value="7">
                        <div className="gmt-value">(GMT +7:00 )</div>
                        <div className="gmt-description"> Bangkok, Hanoi, Jakarta</div> </div>
                    <div className="item" data-value="8">
                        <div className="gmt-value">(GMT +8:00 )</div>
                        <div className="gmt-description"> Beijing, Perth, Singapore, Hong Kong</div> </div>
                    <div className="item" data-value="9">
                        <div className="gmt-value">(GMT +9:00 )</div>
                        <div className="gmt-description"> Tokyo, Seoul</div> </div>
                    <div className="item" data-value="9.5">
                        <div className="gmt-value">(GMT +9:30 )</div>
                        <div className="gmt-description"> ACST (Darwin, Adelaide)</div> </div>
                    <div className="item" data-value="10">
                        <div className="gmt-value">(GMT +10:00 )</div>
                        <div className="gmt-description"> AEST (Brisbane, Sydney), Yakutsk</div> </div>
                    <div className="item" data-value="11">
                        <div className="gmt-value">(GMT +11:00 )</div>
                        <div className="gmt-description"> Vladivostok, Nouméa, Solomon Islands</div> </div>
                    <div className="item" data-value="12">
                        <div className="gmt-value">(GMT +12:00 )</div>
                        <div className="gmt-description"> Auckland, Fiji, Marshall Islands</div> </div>
                    <div className="item" data-value="13">
                        <div className="gmt-value">(GMT +13:00 )</div>
                        <div className="gmt-description"> Samoa</div>
                    </div>
                </div>
        </div>;
    }
}

