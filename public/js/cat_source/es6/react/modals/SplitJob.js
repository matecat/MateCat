
class SplitJobModal extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            numSplit: 2
        };
    }

    changeSplitNumber() {
        this.setState({
            numSplit: this.splitSelect.value
        });
    }

    getChunksWordsTotal() {
        return Math.floor(this.props.job.get('stats').get('TOTAL')/this.state.numSplit);
    }

    render() {
        let wordsTotal = this.getChunksWordsTotal();
        return <div className="modal popup-split">
            <div className="popup">
                <div className="splitbtn-cont">
                    <h3><span className="popup-split-job-id">({this.props.job.get('id')}) </span>
                        <span className="popup-split-job-title">{this.props.job.get('sourceTxt') + " > " + this.props.job.get('targetTxt')}</span>
                    </h3>
                    <span className="label left">Split in</span>
                    <select name="popup-splitselect" className="splitselect left"
                            ref={(select) => this.splitSelect = select} onChange={this.changeSplitNumber}>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                        <option value="6">6</option>
                        <option value="7">7</option>
                        <option value="8">8</option>
                        <option value="9">9</option>
                        <option value="10">10</option>
                        <option value="11">11</option>
                        <option value="12">12</option>
                        <option value="13">13</option>
                        <option value="14">14</option>
                        <option value="15">15</option>
                        <option value="16">16</option>
                        <option value="17">17</option>
                        <option value="18">18</option>
                        <option value="19">19</option>
                        <option value="20">20</option>
                        <option value="21">21</option>
                        <option value="22">22</option>
                        <option value="23">23</option>
                        <option value="24">24</option>
                        <option value="25">25</option>
                        <option value="26">26</option>
                        <option value="27">27</option>
                        <option value="28">28</option>
                        <option value="29">29</option>
                        <option value="30">30</option>
                        <option value="31">31</option>
                        <option value="32">32</option>
                        <option value="33">33</option>
                        <option value="34">34</option>
                        <option value="35">35</option>
                        <option value="36">36</option>
                        <option value="37">37</option>
                        <option value="38">38</option>
                        <option value="39">39</option>
                        <option value="40">40</option>
                        <option value="41">41</option>
                        <option value="42">42</option>
                        <option value="43">43</option>
                        <option value="44">44</option>
                        <option value="45">45</option>
                        <option value="46">46</option>
                        <option value="47">47</option>
                        <option value="48">48</option>
                        <option value="49">49</option>
                        <option value="50">50</option>
                    </select>
                    <span className="label left">Jobs</span>
                </div>
                <div className="popup-box split-box3">
                    <ul className="jobs">
                        <li>
                            <div><h4>Part 1</h4></div>

                            <div className="job-details">
                                <div className="job-perc"><p><span className="aprox">Approx. words:</span><span className="correct none">Words:</span>
                                </p>
                                    {/*<!-- A: la classe Aprox scompare se viene effettuato il calcolo -->*/}
                                    <input type="text" className="input-small" defaultValue={wordsTotal}/>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div><h4>Part 2</h4></div>
                            <div className="job-details">
                                <div className="job-perc"><p><span className="aprox">Approx. words:</span><span className="correct none">Words:</span>
                                </p>
                                    {/*<!-- A: la classe Aprox scompare se viene effettuato il calcolo -->*/}
                                    <input type="text" className="input-small" defaultValue={wordsTotal}/>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div className="total">
                        <p className="wordsum">Total words: <span className="total-w">x</span></p>

                        <p className="error-count current">Current count: <span className="curr-w">x</span></p>

                        <p className="error-count"><span className="txt">Difference</span>: <span className="diff-w">x</span></p>
                        {/*<!-- A:  il p error appare solo se i valori inseriti negli input dei singoli split job non restituiscono la somma totale -->*/}
                    </div>
                    <div className="error-message none">
                        <p>Cannot split in # chunks, do this</p>
                    </div>
                    <div className="cl"></div>
                    <div className="btnsplit">

                        <a id="exec-split" className="uploadbtn loader">
                            <span className="uploadloader"></span>
                            <span className="text">Check</span>
                        </a>
                        <a id="exec-split-confirm" className="splitbtn done none">
                            <span className="text">Confirm</span>
                        </a>
                        <span className="btn fileinput-button btn-cancel right">
                    <span>Cancel</span>
                </span>
                    </div>
                </div>
                {/*<!-- END DIV SPLIT BOX -->*/}
            </div>
            {/*<!-- END DIV POPUP-SPLIT INTERNO -->*/}
        </div>;
    }
}


export default SplitJobModal ;
