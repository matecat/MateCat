
let AnalyzeConstants = require('../../constants/AnalyzeConstants');

class JobAnalyze extends React.Component {

    constructor(props) {
        super(props);
    }

    getSplitHtml() {
        return <div className="splitbtn-cont pull-right">
            <span className="label left">Split in</span>
            <select name="" className="splitselect">
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

            <a href="#" className="dosplit splitbtn disabled" title="You cannot split a job with 1 or 0 payable words.">Split</a>

        </div>
    }

    get

    componentDidUpdate() {
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    shouldComponentUpdate(nextProps, nextState){
        return true;
    }

    render() {
        var splitHtml = this.getSplitHtml();
        // If splitted add class 'splitted'
        return <div className="jobcontainer">
            <div>

                <h3>
                    <span className="source_lang">English</span> > <span className="target_lang">Italian</span>
                </h3>

                {splitHtml}
                <div className="nosplit"><a href="#" className="domerge mergebtn">Merge all</a></div>
                <!-- END CONTAINER SPLIT JOBS BUTTONS  -->


                <div>
                    <table id="1244" className="tablestats">
                        <thead>
                        <tr>
                            <th></th>

                            {/*If config.enable_outsource*/}
                            <th tal:condition="enable_outsource">Payable</th>

                            <th>Total</th>
                            <th className="new" width="100">New</th>
                            <th className="repetition">Repetition</th>
                            <th className="internal-matches">Internal Matches<br/>
                                (75%-99%)
                            </th>
                            <th className="tm-partial">TM<br/>
                                Partial (50%-74%)
                            </th>

                            {/*tal:condition="exists:id_for_job/rates/75%-99%"*/}
                            <th className="tm-partial">TM<br/>
                                Partial (75%-99%)
                            </th>
                            {/*tal:condition="not: exists: id_for_job/rates/75%-99%"*/}
                            <th className="tm-partial" >TM<br/>
                                Partial (75%-84%)
                            </th>
                            {/*tal:condition="not: exists: id_for_job/rates/75%-99%"*/}
                            <th className="tm-partial" >TM<br/>
                                Partial (85%-94%)
                            </th>
                            {/*tal:condition="not: exists: id_for_job/rates/75%-99%"*/}
                            <th className="tm-partial" >TM<br/>
                                Partial (95%-99%)
                            </th>
                            <th className="tm-100">TM 100%</th>
                            {/*tal:condition="exists: id_for_job/rates/100%_PUBLIC"*/}
                            <th className="tm-100" >Public TM 100%</th>
                            <th className="tm-100-context">TM 100% in context</th>
                            <th className="mt">Machine Translation</th>
                            <th className="empty"/>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>

                            {/*tal:condition="enable_outsource"*/}
                            <td  className="payable-rate-breakdown" colspan="3">Payable Rate Breakdown</td>
                            {/*tal:condition="not:enable_outsource"*/}
                            <td className="payable-rate-breakdown" colspan="2"/>

                            {/*Value = ${id_for_job/rates/NO_MATCH}%*/}
                            <td className="editarea"/>
                            {/*${id_for_job/rates/REPETITIONS}%*/}
                            <td className="editarea"/>
                            {/*${id_for_job/rates/INTERNAL}%*/}
                            <td className="editarea"></td>
                            {/*${id_for_job/rates/50%-74%}%*/}
                            <td className="editarea"></td>
                            {/*tal:condition="exists:id_for_job/rates/75%-99%"*/}
                            {/*${id_for_job/rates/75%-99%}%*/}
                            <td className="editarea" ></td>
                            {/*tal:condition="not: exists: id_for_job/rates/75%-99%"*/}
                            {/*${id_for_job/rates/75%-84%}%*/}
                            <td className="editarea" ></td>
                            {/*tal:condition="not: exists: id_for_job/rates/75%-99%"*/}
                            {/*${id_for_job/rates/85%-94%}%*/}
                            <td className="editarea" ></td>
                            {/*tal:condition="not: exists: id_for_job/rates/75%-99%"*/}
                            {/*${id_for_job/rates/95%-99%}%*/}
                            <td className="editarea" ></td>
                            {/*${id_for_job/rates/100%}%*/}
                            <td className="editarea" ></td>
                            {/*tal:condition="exists: id_for_job/rates/100%_PUBLIC"*/}
                            {/*${id_for_job/rates/100%_PUBLIC}%*/}
                            <td className="editarea" ></td>
                            <td className="editarea">0%</td>
                            {/*${id_for_job/rates/MT}%*/}
                            <td className="editarea"></td>
                            <td className="empty"></td>
                        </tr>

                        <tr className="tablespace">
                            <td colspan="14">&nbsp;</td>
                        </tr>
                        </tbody>

                        <tal:block tal:define="job php:array()" tal:repeat="job id_for_job/chunks">
                            <tbody className="tablestats" data-jid="${job/jid}" data-pwd="${job/jpassword}">
                            <tr className="totaltable">
                                <td className="languages">
                                    <span tal:condition="splitted" className="splitnum left">${job/jid}-${repeat/job/number}</span>
                                    <span tal:condition="not:splitted" className="splitnum left">${job/jid}</span>
                                    <a href="#" className="filedetails part3">File details</a>
                                    <span className="numfiles">(<span tal:define="files job/files; f php:array()" tal:content="php:count(files)">0</span>)</span>
                                </td>
                                <td tal:condition="enable_outsource" className="stat-payable">
                                    <strong className="stat_tot" tal:content="job/total_eq_word_count_print">11,500</strong></td>
                                <td className="stat-total" tal:content="job/total_raw_word_count_print">13,500</td>
                                <td className="stat_new">0</td>
                                <td className="stat_rep">0</td>
                                <td className="stat_int">0</td>
                                <td className="stat_tm50">0</td>
                                <td className="stat_tm75" tal:condition="exists:id_for_job/rates/75%-99%">0</td>
                                <td className="stat_tm75_84" tal:condition="not: exists: id_for_job/rates/75%-99%">0</td>
                                <td className="stat_tm85_94" tal:condition="not: exists: id_for_job/rates/75%-99%">0</td>
                                <td className="stat_tm95_99" tal:condition="not: exists: id_for_job/rates/75%-99%">0</td>
                                <td className="stat_tm100">0</td>
                                <td className="stat_tm100_public" tal:condition="exists: id_for_job/rates/100%_PUBLIC">0</td>
                                <td className="stat_tmic">0</td>
                                <td className="stat_mt">0</td>
                                <td className="empty">
                                    <a tal:attributes="href string:${basepath}translate/${pname}/${job/source_short}-${job/target_short}/${job/jid}-${job/jpassword}" href="#" target="_blank" className="uploadbtn translate">Translate</a>
                                </td>
                            </tr>
                            <tal:block tal:define="files job/files; f php:array()" tal:repeat="f files">
                                <tr tal:attributes="id string:file_${job/jid}_${job/jpassword}_${f/id}" id="file_1703" className="subfile part3files">
                                    <td className="stat-name">
                                        <p className="filename" tal:content="f/filename" tal:attributes='title f/filename'>
                                            filename1.xliff</p></td>
                                    <td tal:condition="enable_outsource" className="stat_payable"><strong tal:content="f/file_eq_word_count">6,500</strong>
                                    </td>
                                    <td className="stat-total" tal:content="f/file_raw_word_count">7,500</td>
                                    <td className="stat_new">0</td>
                                    <td className="stat_rep">0</td>
                                    <td className="stat_int">0</td>
                                    <td className="stat_tm50">0</td>
                                    <td className="stat_tm75" tal:condition="exists:id_for_job/rates/75%-99%">0</td>
                                    <td className="stat_tm75_84" tal:condition="not: exists: id_for_job/rates/75%-99%">0</td>
                                    <td className="stat_tm85_94" tal:condition="not: exists: id_for_job/rates/75%-99%">0</td>
                                    <td className="stat_tm95_99" tal:condition="not: exists: id_for_job/rates/75%-99%">0</td>
                                    <td className="stat_tm100">0</td>
                                    <td className="stat_tm100_public" tal:condition="exists: id_for_job/rates/100%_PUBLIC">0</td>
                                    <td className="stat_tmic">0</td>
                                    <td className="stat_mt">0</td>
                                    <td className="empty"></td>
                                </tr>

                            </tal:block>

                            </tbody>
                        </tal:block>

                    </table>
                </div>


            </div>
        </div>;


    }
}

export default JobAnalyze;
