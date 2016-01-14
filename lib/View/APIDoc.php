<?php
require_once '../../inc/Bootstrap.php';
Bootstrap::start();

$count = 0;
foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
    $count += count( $value );
}

$nr_supoported_files = $count;

$max_file_size_in_MB = INIT::$MAX_UPLOAD_FILE_SIZE / (1024 * 1024);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>API - Matecat</title>
    <link href="/public/css/style.css" rel="stylesheet" type="text/css" />
    <link href="/public/css/manage.css" rel="stylesheet" type="text/css" />
    <link href="/public/css/common.css" rel="stylesheet" type="text/css" />
    <script src="/public/js/lib/jquery.js"></script>
</head>
<body class="api">
<header>
    <div class="wrapper ">
        <a href="/" class="logo"></a>
    </div>
</header>
<div id="contentBox" class="wrapper">
    <div class="colsx">
        <a href="#top"><span class="logosmall"></span></a>
        <h1>API</h1>
        <ul class="menu">
            <li><a href="#new-post">/new (POST)</a></li>
            <li><a href="#status-post">/status (GET)</a></li>
            <li><a href="#change_project_password-post">/change_project_password (POST)</a></li>
            <li><a href="#file-format">Supported file format</a></li>
            <li><a href="#languages">Supported languages</a></li>
            <li><a href="#subjects">Supported subjects</a></li>
            <li><a href="#seg-rules">Supported segmentation rules</a></li>
        </ul>
    </div>
    <div class="coldx">
        <a name="top" class="top"></a>
        <div class="block">
            <a name="new-post"><h3 class="method-title">/new (POST)</h3></a>
            <dl>
                <dt>Description</dt>
                <dd><p>Create a new Project.</p>
                </dd>
                <dt class="url-label">URL Structure</dt>
                <dd>
                    <pre class="literal-block"><?=INIT::$HTTPHOST . INIT::$BASEURL?><b>api</b>/new</pre>
                </dd>
                <dt>Method</dt>
                <dd>POST ( multipart/form-data )</dd>
                <dt>Files To Upload</dt>
                <dd>
                    <p><span class="req">required</span> The file(s) to be uploaded. You may also upload your own translation memories (TMX)</p>
<!--                    <p><span class="opt">optional</span> The TMX(s) to be uploaded.</p>-->
                </dd>
                <dt>Parameters</dt>
                <dd>
                    <ul class="parameters">
                        <li><span class="req">required</span> <code class="param">project_name</code> <code>(string)</code> The name of the project you want
                            create.
                        </li>
                        <li><span class="req">required</span> <code class="param">source_lang</code> <code>(string)</code> <a href="http://www.rfc-editor.org/rfc/rfc5646.txt" target="blank">RFC 5646</a> language+region Code ( en-US <b>case sensitive</b> ) as specified in <a href="http://www.w3.org/International/articles/language-tags/" target="blank">W3C standards</a>
                        </li>
                        <li><span class="req">required</span> <code class="param">target_lang</code> <code>(string)</code> <a href="http://www.rfc-editor.org/rfc/rfc5646.txt" target="blank">RFC 5646</a> language(s)+region(s) Code(s)  as specified in <a href="http://www.w3.org/International/articles/language-tags/" target="blank">W3C standards</a>.
                            <br />Multiple languages must be comma separated ( <code>it-IT,fr-FR,es-ES</code> <b>case sensitive</b>)
                        </li>
                        <li><span class="opt">optional (default: 1)</span> <code class="param">tms_engine</code> <code>(int)</code> Identifier for Memory Server
                            <code>0</code> means disabled, <code>1</code> means MyMemory)
                        </li>
                        <li><span class="opt">optional (default: 1)</span> <code class="param"> mt_engine</code> <code>(int)</code> Identifier for Machine Translation Service
                            <code>0</code> means disabled, <code>1</code> means get MT from MyMemory)
                        </li>
                        <li><span class="opt">optional</span> <code class="param"> private_tm_key</code> <code>(string)</code>
                            Private key(s) for MyMemory.
                            <br />- If a TMX file is uploaded and no key is provided, a <code>new</code> key will be created.
                            <br />- Existing MyMemory private keys or <code>new</code> to create a new key.
                            <br />- Multiple keys must be comma separated. Up to 5 keys allowed. (<code>xxx345cvf,new,s342f234fc</code>)
                            <br />- Only available if <code>tms_engine</code> is set to 1 or if is not used
                            <br />
                            <b>Be careful! All TMX files provided will be uploaded only into the first tm key. <br/>
                                Other keys will have no content at the project start time and will be populated with the contributions coming from the translation process <br/>
                                All the keys provided will join the project in read/write mode
                            </b>

                        </li>
                        <li><span class="opt">optional  (default: general)</span> <code class="param"> subject</code> <code>(string)</code> The subject of the project you want to create.
                        </li>
                        <li><span class="opt">optional</span> <code class="param"> segmentation_rule</code> <code>(string)</code> The segmentation rule you want to use to parse your file .
                        </li>
                        <li><span class="opt">optional</span> <code class="param"> owner_email (default: anonymous)</code> <code>(string)</code> The email of the owner of the project.
                        </li>
                    </ul>
                </dd>
                <dt>Returns</dt>
                <dd>
                    <p>The metadata for the created project.</p>

                    <p>More information on the returned metadata fields are available
                        <a href="<?=INIT::$HTTPHOST . INIT::$BASEURL?>api/docs#metadata-new-details">here</a>
                    </p>

                    <p>A complete list of accepted languages in the right format are available
                        <a href="<?=INIT::$HTTPHOST . INIT::$BASEURL?>api/docs#file-format">here</a>
                    </p>

                    <p><strong>Sample JSON response</strong></p>
                            <pre class="literal-block">
{
    "status": "OK",
    "id_project": 5368,
    "project_pass": "76ba60c027b9",
    "new_keys": "sdfasdfasf23r,23ffqwefqef3f"
}                           </pre>
                    <p><strong>Return value definitions</strong></p>
                    <table id="metadata-new-details" class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tbody>
                        <tr>
                            <th>field</th>
                            <th>description</th>
                        </tr>
                        <tr>
                            <td><code>status</code></td>
                            <td>Return the creation status of the project. The statuses can be:
                                <ul>
                                    <li><code>OK</code> indicating that the creation worked.</li>
                                    <li><code>FAIL</code> indicating that the creation is failed.</li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td><code>id_project</code></td>
                            <td>Return the unique id of the project just created.
                                If creation status is <code>FAIL</code> this key will simply be omitted from the result.
                            </td>
                        </tr>
                        <tr>
                            <td><code>project_pass</code></td>
                            <td>Return the password of the project just created.
                                If creation status is <code>FAIL</code> this key will simply be omitted from the result.
                            </td>
                        </tr>
                        <tr>
                            <td><code>new_keys</code></td>
                            <td>If you specified <code>new</code> as one or more value in the <code>private_tm_key</code> parameter,
                                the new created keys are returned as CSV string (<code>4rcf34rc,r34rcfewf3r2</code>)<br/>
                                Otherwise <code>empty</code> string is returned
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <p><strong>Errors</strong></p>
                    <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">

                        <tbody>
                        <tr>
                            <th>status</th>
                            <th>message</th>
                        </tr>
                        <tr>
                            <td>FAIL</td>
                            <td>The project creation is failed</td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                        <pre class="literal-block">
{
	status: "FAIL"
	message: "Project Conversion Failure"
	debug: [2]
	0:  {
		code: -110
		message: "Error: there is a problem with this file, it cannot be converted back to the original one."
		debug: "TEST_FAILURE_DOC1.docx"
	}
	1:  {
		code: -100
		message: "Conversion error. Try opening and saving the document with a new name. If this does not work, try converting to DOC."
		debug: "TEST_FAILURE_DOC2.docx"
	}

}                                      </pre>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <p><strong>Debug Codes</strong></p>
                    <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">

                        <tbody>
                        <tr>
                            <th>code</th>
                            <th>message</th>
                            <th>debug</th>
                        </tr>
                        <tr>
                            <td>-1</td>
                            <td>"Error: missing file name."</td>
                            <td>NULL</td>
                        </tr>
                        <tr>
                            <td>-6</td>
                            <td>"Error during upload. Please retry."</td>
                            <td>NULL</td>
                        </tr>
                        <tr>
                            <td>-100</td>
                            <td>"Conversion error. Try opening and saving the document with a new name. If this does not work, try converting to DOC."</td>
                            <td>The failed file name. </td>
                        </tr>
                        <tr>
                            <td>-101</td>
                            <td>"Error: failed to save converted file from cache to disk"</td>
                            <td>The failed file name. </td>
                        </tr>
                        <tr>
                            <td>-102</td>
                            <td>"Error: File too large"</td>
                            <td>The failed file name. </td>
                        </tr>
                        <tr>
                            <td>-103</td>
                            <td>"Error: failed to save file on disk"</td>
                            <td>The failed file name. </td>
                        </tr>
                        <tr>
                            <td>-110</td>
                            <td>"Error: there is a problem with this file, it cannot be converted back to the original one."</td>
                            <td>The failed file name. </td>
                        </tr>
                        </tbody>
                    </table>

                </dd>
                <dt>Notes</dt>
                <dd><p><code>/new</code> has a maximum file size limit of <?= $max_file_size_in_MB ?> MB per file and a max number of files of <?= INIT::$MAX_NUM_FILES ?>.</p></dd>
                <dd><p>Matecat PRO accept only <?= $nr_supoported_files ?> file formats. A list of all accepted file are available
                        <a href="<?=INIT::$HTTPHOST . INIT::$BASEURL?>api/docs#file-format">here</a></p>
                </dd>
            </dl>

            <a class="gototop" href="#top">Go to top</a>
        </div>
        <div class="block">
            <a name="status-post"><h3 class="method-title">/status (GET)</h3></a>
            <dl>
                <dt>Description</dt>
                <dd><p>Retrieve the status of a project</p>
                </dd>
                <dt class="url-label">URL Structure</dt>
                <dd>
                    <pre class="literal-block"><?=INIT::$HTTPHOST . INIT::$BASEURL?><b>api</b>/status/?<code>id_project=<12345></code>&<code>project_pass=<1abcde123></abcde123></code></pre>
                </dd>
                <dt>Method</dt>
                <dd>GET</dd>
                <dt>Parameters</dt>
                <dd>
                    <ul class="parameters">
                        <li><span class="req">required</span> <code class="param"> id_project</code> <code>(int)</code> The identifier of the project, should be the
                            value returned by the <a href="<?=INIT::$HTTPHOST . INIT::$BASEURL?>api/docs#new-post"><code>/new</code></a> method.
                        </li>
                        <li><span class="req">required</span> <code class="param"> project_pass</code> <code>(string)</code> The password associated with the project, should be the
                            value returned by the <a href="<?=INIT::$HTTPHOST . INIT::$BASEURL?>api/docs#new-post"><code>/new</code></a> method ( associated with the id_project )
                        </li>
                    </ul>
                </dd>
                <dt>Returns</dt>
                <dd>
                    <p>The metadata for the created project containing the status of the project.</p>

                    <p>More information on the returned metadata fields are available
                        <a href="<?=INIT::$HTTPHOST . INIT::$BASEURL?>api/docs#metadata-status-details">here</a>
                    </p>

                    <p><strong>Sample JSON response</strong></p>
                                    <pre class="literal-block">
{
  "errors": [],
  "data": {
    "jobs": {
      "19": {
        "chunks": {
          "2bfe688ec780": {
            "34": {
              "TOTAL_PAYABLE": [
                166.4,
                "166"
              ],
              "REPETITIONS": [
                4,
                "4"
              ],
              "MT": [
                170,
                "170"
              ],
              "NEW": [
                0,
                "0"
              ],
              "TM_100": [
                9,
                "9"
              ],
              "TM_75_99": [
                26,
                "26"
              ],
              "TM_50_74": [
                0,
                "0"
              ],
              "INTERNAL_MATCHES": [
                4,
                "4"
              ],
              "ICE": [
                0,
                "0"
              ],
              "NUMBERS_ONLY": [
                0,
                "0"
              ],
              "FILENAME": "Localizable.german+tagged.strings"
            }
          }
        },
        "totals": {
          "2bfe688ec780": {
            "TOTAL_PAYABLE": [
              166.4,
              "166"
            ],
            "REPETITIONS": [
              4,
              "4"
            ],
            "MT": [
              170,
              "170"
            ],
            "NEW": [
              0,
              "0"
            ],
            "TM_100": [
              9,
              "9"
            ],
            "TM_75_99": [
              26,
              "26"
            ],
            "TM_50_74": [
              0,
              "0"
            ],
            "INTERNAL_MATCHES": [
              4,
              "4"
            ],
            "ICE": [
              0,
              "0"
            ],
            "NUMBERS_ONLY": [
              0,
              "0"
            ]
          }
        }
      }
    },
    "summary": {
      "IN_QUEUE_BEFORE": 0,
      "STATUS": "DONE",
      "TOTAL_SEGMENTS": 84,
      "SEGMENTS_ANALYZED": 82,
      "TOTAL_FAST_WC": "208.60",
      "TOTAL_TM_WC": 166.4,
      "TOTAL_STANDARD_WC": 191.9,
      "STANDARD_WC_TIME": "31",
      "FAST_WC_TIME": "33",
      "TM_WC_TIME": "27",
      "STANDARD_WC_UNIT": "minutes",
      "TM_WC_UNIT": "minutes",
      "FAST_WC_UNIT": "minutes",
      "USAGE_FEE": "1.27",
      "PRICE_PER_WORD": "0.030",
      "DISCOUNT": "1",
      "NAME": "Localizable.german_tagged.strings",
      "TOTAL_RAW_WC": 213,
      "TOTAL_PAYABLE": 166.4,
      "PAYABLE_WC_TIME": "27",
      "PAYABLE_WC_UNIT": "minutes",
      "DISCOUNT_WC": "0"
    }
  },
  "status": "DONE",
  "analyze": "/analyze/Localizable.german_tagged.strings/19-ef48a60d432f",
  "jobs": {
    "langpairs": {
      "19-2bfe688ec780": "de-DE|en-US"
    },
    "job-url": {
      "19-2bfe688ec780": "/translate/Localizable.german_tagged.strings/de-DE-en-US/19-2bfe688ec780"
    },
    "job-quality-details": {
      "19-2bfe688ec780": [
        {
          "type": "Typing",
          "allowed": 0.3,
          "found": 0,
          "vote": "Excellent"
        },
        {
          "type": "Translation",
          "allowed": 0.3,
          "found": 0,
          "vote": "Excellent"
        },
        {
          "type": "Terminology",
          "allowed": 0.5,
          "found": 0,
          "vote": "Excellent"
        },
        {
          "type": "Language Quality",
          "allowed": 0.5,
          "found": 0,
          "vote": "Excellent"
        },
        {
          "type": "Style",
          "allowed": 0.8,
          "found": 0,
          "vote": "Excellent"
        }
      ]
    },
    "quality-overall": {
      "19-2bfe688ec780": "Excellent"
    }
  }
}
</pre>
                    <p><strong>Return value definitions</strong></p>
                    <table id="metadata-status-details" class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">

                        <tbody>
                        <tr>
                            <th>field</th>
                            <th>description</th>
                        </tr>
                        <tr>
                            <td><code>errors</code></td>
                            <td>A list of objects containing error message at system wide level. Every error has a negative numeric code and a textual message ( currently the only error reported is the wrong version number in <code>config.inc.php</code> file and happens only after Matecat updates, so you should never see it ).
                            </td>
                        </tr>
                        <tr>
                            <td><code>data</code></td>
                            <td>Holds all progress statisticts for every <code>job</code> and for overall project.<br/>
                                It contains <code>jobs</code> and <code>summary</code> sub-sections.
                            </td>
                        </tr>
                        <tr>
                            <td><code>summary</code></td>
                            <td>Sub-section <code>summary</code> holds statistict for the whole project that are not related to single <code>job</code> objects:
                                <ul>
                                    <li><code>NAME</code>: the name of your project</li>
                                    <li><code>STATUS</code>: the status the project is from analysis perspective
                                        <ol>
                                            <li><code>NEW</code>: just created, not analyzed yet</li>
                                            <li><code>FAST_OK</code>: preliminary ("fast") analysis completed, now running translations ("TM") analysis</li>
                                            <li><code>DONE</code>: analysis complete</li>
                                        </ol>
                                    </li>
                                    <li><code>IN_QUEUE_BEFORE</code>: number of segments belonging to other projects that are being analyzed before yours; it's the wait time for you</li>
                                    <li><code>TOTAL_SEGMENTS</code>: number of segments belonging to your project</li>
                                    <li><code>SEGMENTS_ANALYZED</code>: analysis progress, on <code>TOTAL_SEGMENTS</code></li>
                                    <li><code>TOTAL_RAW_WC</code>: number of words (word count) of your project, as extracted by the textual parsers</li>
                                    <li><code>TOTAL_STANDARD_WC</code>: word count, minus the sentences that are repeated</li>
                                    <li><code>TOTAL_FAST_WC</code>: word count, minus the sentences that are partially repeated</li>
                                    <li><code>TOTAL_TM_WC</code>: word count, with sentences found in the cloud translation memory discounted from the total; this depends on the percentage of overlapping between the sentences of your project and the past translations</li>
                                    <li><code>TOTAL_PAYABLE</code>: total word count, after analysis</li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td><code>jobs</code></td>
                            <td>Sub-section <code>jobs</code> holds statistict for all the <code>job</code> objects.<br/>
                                The numerical keys on the first level are the IDs of the jobs contained in the project.<br/>
                                Each job identifies a target language; as such, there is a 1-1 mapping between ID and target languages in your project.<br/>
                                A job holds a <code>chunks</code> and a <code>totals</code> section.
                            </td>
                        </tr>
                        <tr>
                            <td><code>totals</code></td>
                            <td>
                                Contains all analysis statistics for all files in the current job (i.e., all files that have to be translated in a target language):
                                <ul>
                                    <li><code>TOTAL_PAYABLE</code>: total word count, after analysis</li>
                                    <li><code>REPETITIONS</code>: cumulative word count for the segments that repeat themselves in the file</li>
                                    <li><code>INTERNAL_MATCHES</code>: cumulative word count for the segments that fuzzily overlap with others in the file, while not being an exact repetition</li>
                                    <li><code>MT</code>: cumulative word count for all segments that can be translated with machine translation; it accounts for all the information that could not be discounted by repetitions, internal matches or translation memory</li>
                                    <li><code>NEW</code>: cumulative word count for segments that can't be discounted with repetition or internal matches; it's the net translation effort</li>
                                    <li><code>TM_100</code>: cumulative word count for the exact matches found in TM server</li>
                                    <li><code>TM_75_99</code>: cumulative word count for partial matches in the TM that cover 75-99% of each segment</li>
                                    <li><code>ICE</code>: cumulative word count for 100% TM matches that also share the same context with the TM</li>
                                    <li><code>NUMBERS_ONLY</code>: cumulative word counts for segments made of numberings, dates and similar not translatable data ( i.e.: 93/127 )</li>
                                </ul>
                        </tr>
                        <tr>
                            <td><code>chunks</code></td>
                            <td>A structure modeling a portion of content to translate. <br/>
                                A whole file can be splitted in multiple chunks, to be distributed to multiple translators, or can be enveloped in a single chunk.<br/>
                                Each chunk has a password as first level key and a numerical ID as second level key to identify different chunks for the same file.<br/>
                                Each chunk contains the same structure of the <code>totals</code> section.<br/>
                                The sum of the <code>chunks</code> equals to the <code>totals</code>.
                            </td>
                        </tr>
                        <tr id="project-status">
                            <td><code>status</code></td>
                            <td>The analysis status of the project:
                                <ul>
                                    <li><code>ANALYZING</code>: analysis/creation still in progress</li>
                                    <li><code>NO_SEGMENTS_FOUND</code>: the project has no segments to analyze (have you uploaded a file containing only images?)</li>
                                    <li><code>ANALYSIS_NOT_ENABLED</code>: no analysis will be performed because of Matecat configuration</li>
                                    <li><code>DONE</code>: the analysis/creation is completed.</li>
                                    <li><code>FAIL</code>: the analysis/creation is failed.</li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td><code>analyze</code></td>
                            <td>A link to the analyze page; it's a human readable version of this API output</td>
                        </tr>
                        <tr>
                            <td><code>jobs</code></td>
                            <td>Section <code>jobs</code> contains all metadata about <code>job</code> (like URIs, quality reports and languages):
                                <ul>
                                    <li><code>langpairs</code>: the language pairs for your project; an entry for every chunk in the project, with the id-password combination as key and the language pair as the value
                                    </li>
                                    <li><code>job-url</code>: the links to the chunks of the project; an entry for every chunk in the project, with the id-password combination as key and the link to the chunk as the value.</li>
                                    <li><code>job-quality-details</code>: a structure containing, for each chunk, an array of 5 objects: each object is a quality check performed on the job; the object contains the type of the check (<code>Typing</code>, <code>Translation</code>, <code>Terminology</code>, <code>Language Quality</code>, <code>Style</code>), the quantity of errors found, the allowed errors threshold and the rating given by the errors/threshold ratio (same as <code>quality-overall</code>)
                                    </li>
                                    <li><code>quality-overall</code>: the overall quality rating for each chunk (<code>Very good</code>, <code>Good</code>, <code>Acceptable</code>, <code>Poor</code>, <code>Fail</code>)</li>
                                </ul>
                        </tr>
                        </tbody>
                    </table>
                </dd>
                <dt>Errors</dt>
                <dd>
                    <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">

                        <tbody>
                        <tr>
                            <th>field</th>
                            <th>description</th>
                        </tr>
                        <tr>
                            <td>FAIL</td>
                            <td>Wrong Password. Access denied</td>
                        </tr>
                        <tr>
                            <td>FAIL</td>
                            <td>No id project provided</td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                                <pre class="literal-block">
{
    "status":  "FAIL",
    "message": "Wrong Password. Access denied"
}                                               </pre>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </dd>
            </dl>
            <a class="gototop" href="#top">Go to top</a>
        </div>

        <!-- change Password Block -->
        <div class="block">
            <a name="change_project_password-post"><h3 class="method-title">/change_project_password (POST)</h3></a>
            <dl>
                <dt>Description</dt>
                <dd><p>Change the password of a project.</p>
                </dd>
                <dt class="url-label">URL Structure</dt>
                <dd>
                    <pre class="literal-block"><?=INIT::$HTTPHOST . INIT::$BASEURL?><b>api</b>/change_project_password</pre>
                </dd>
                <dt>Method</dt>
                <dd>POST ( application/x-www-form-urlencoded )</dd>
                <dt>Parameters</dt>
                <dd>
                    <ul class="parameters">
                        <li><span class="req">required</span> <code class="param">id_project</code> <code>(int)</code>
                            The id of the project you want to update.
                        </li>
                        <li><span class="req">required</span> <code class="param">old_pass</code>
                            <code>(string)</code> The OLD password of the project you want to update.</a>
                        </li>
                        <li><span class="req">required</span> <code class="param">new_pass</code>
                            <code>(string)</code> The NEW password of the project you want to update.</a>
                        </li>
                    </ul>
                </dd>
                <dt>Returns</dt>
                <dd>
                    <p>The result status for the request.</p>

                    <p><strong>Sample JSON response</strong></p>
                                <pre class="literal-block">
{
    "status": "OK",
    "id_project": "5425",
    "project_pass": "3cde561e42d1"
}                              </pre>
                    <p><strong>Return value definitions</strong></p>
                    <table id="metadata-change_project_password" class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tbody>
                        <tr>
                            <th>field</th>
                            <th>description</th>
                        </tr>
                        <tr>
                            <td><code>status</code></td>
                            <td>Return the exit status of the action. The statuses can be:
                                <ul>
                                    <li>
                                        <code>OK</code> indicating that the action worked.
                                    </li>
                                    <li><code>FAIL</code> indicating that the action failed because of the project was
                                        not found.
                                    </li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td><code>id_project</code></td>
                            <td>Returns the id of the project just updated.</td>
                        </tr>
                        <tr>
                            <td><code>project_pass</code></td>
                            <td>Returns the new pass of the project just updated.</td>
                        </tr>
                        <tr>
                            <td><code>message</code></td>
                            <td>Return the error message for the action if the status is <code>FAIL</code></td>
                        </tr>
                        </tbody>
                    </table>

                    <p><strong>Errors</strong></p>
                    <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">

                        <tbody>
                        <tr>
                            <th>status</th>
                            <th>message</th>
                        </tr>
                        <tr>
                            <td>FAIL</td>
                            <td>Wrong id or pass</td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                            <pre class="literal-block">
{
    "status": "FAIL",
    "message": "Wrong id or pass"
}                                           </pre>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </dd>
            </dl>

            <a class="gototop" href="#top">Go to top</a>
        </div>
        <!-- END change Password Block -->

        <div class="block">
            <a name="file-format"><h3 class="method-title">Supported file formats</h3></a>


            <table class="tablestats fileformat" width="100%" border="0" cellspacing="0" cellpadding="0">

                <thead>
                <tr><th width="40%">Office</th>
                    <th width="15%">Web</th>
                    <th width="15%">Interchange Formats</th>
                    <th width="15%">Desktop Publishing</th>
                    <th width="15%">Localization</th>
                </tr></thead>
                <tbody><tr>
                    <td>
                        <ul class="office">
                            <li><span class="extdoc">doc</span></li>
                            <li><span class="extdoc">dot</span></li>
                            <li><span class="extdoc">docx</span></li>
                            <li><span class="extdoc">dotx</span></li>
                            <li><span class="extdoc">docm</span></li>
                            <li><span class="extdoc">dotm</span></li>
                            <li><span class="extdoc">rtf</span></li>
                            <li><span class="extdoc">odt</span></li>
                            <li><span class="extdoc">sxw</span></li>
                            <li><span class="exttxt">txt</span></li>
                            <li><span class="extpdf">pdf</span></li>
                            <li><span class="extxls">xls</span></li>
                            <li><span class="extxls">xlt</span></li>
                            <li><span class="extxls">xlsm</span></li>
                            <li><span class="extxls">xlsx</span></li>
                            <li><span class="extxls">xltx</span></li>
                            <li><span class="extxls">ods</span></li>
                            <li><span class="extxls">sxc</span></li>
                            <li><span class="extxls">csv</span></li>
                            <li><span class="extppt">pot</span></li>
                            <li><span class="extppt">pps</span></li>
                            <li><span class="extppt">ppt</span></li>
                            <li><span class="extppt">potm</span></li>
                            <li><span class="extppt">potx</span></li>
                            <li><span class="extppt">ppsm</span></li>
                            <li><span class="extppt">ppsx</span></li>
                            <li><span class="extppt">pptm</span></li>
                            <li><span class="extppt">pptx</span></li>
                            <li><span class="extppt">odp</span></li>
                            <li><span class="extppt">sxi</span></li>
                            <li><span class="extxml">xml</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="exthtm">htm</span></li>
                            <li><span class="exthtm">html</span></li>
                            <li><span class="exthtm">xhtml</span></li>
                            <li><span class="extxml">xml</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extxif">xliff</span></li>
                            <li><span class="extxif">sdlxliff</span></li>
                            <li><span class="exttmx">tmx</span></li>
                            <li><span class="extttx">ttx</span></li>
                            <li><span class="extitd">itd</span></li>
                            <li><span class="extxlf">xlf</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extmif">mif</span></li>
                            <li><span class="extidd">inx</span></li>
                            <li><span class="extidd">idml</span></li>
                            <li><span class="extidd">icml</span></li>
                            <li><span class="extqxp">xtg</span></li>
                            <li><span class="exttag">tag</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extdit">dita</span></li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li><span class="extpro">properties</span></li>
                            <li><span class="extrcc">rc</span></li>
                            <li><span class="extres">resx</span></li>
                            <li><span class="extxml">xml</span></li>
                            <li><span class="extdit">dita</span></li>
                            <li><span class="extsgl">sgml</span></li>
                            <li><span class="extsgm">sgm</span></li>
                            <li><span class="extxml">Android xml</span></li>
                            <li><span class="extstr">strings</span></li>
                        </ul>
                    </td>
                </tr>
                </tbody></table>


            <a class="gototop" href="#top">Go to top</a>
        </div>

        <div class="block">
            <a name="languages"><h3 class="method-title">Supported languages</h3></a>


            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <th>
                    Language ( Code )
                </th>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <ul class="lang-list">
                            <li>Afrikaans (af-ZA)</li>
                            <li>Albanian (sq-AL)</li>
                            <li>Arabic (ar-SA)</li>
                            <li>Armenian (hy-AM)</li>
                            <li>Basque (eu-ES)</li>
                            <li>Bengali (bn-IN)</li>
                            <li>Bielarus (be-BY)</li>
                            <li>Bosnian (bs-BA)</li>
                            <li>Breton (br-FR)</li>
                            <li>Bulgarian (bg-BG)</li>
                            <li>Catalan (ca-ES)</li>
                            <li>Chinese Simplified (zh-CN)</li>
                            <li>Chinese Traditional (zh-TW)</li>
                            <li>Croatian (hr-HR)</li>
                            <li>Czech (cs-CZ)</li>
                            <li>Danish (da-DK)</li>
                            <li>Dutch (nl-NL)</li>
                            <li>English (en-GB)</li>
                            <li>English US (en-US)</li>
                            <li>Estonian (et-EE)</li>
                            <li>Faroese (fo-FO)</li>
                            <li>Finnish (fi-FI)</li>
                            <li>Flemish (nl-BE)</li>
                            <li>French (fr-FR)</li>
                            <li>Galician (gl-ES)</li>
                            <li>Georgian (ka-GE)</li>
                            <li>German (de-DE)</li>
                            <li>Greek (el-GR)</li>
                            <li>Gujarati (gu-IN)</li>
                            <li>Hebrew (he-IL)</li>
                            <li>Hindi (hi-IN)</li>
                            <li>Hungarian (hu-HU)</li>
                            <li>Icelandic (is-IS)</li>
                            <li>Indonesian (id-ID)</li>
                            <li>Irish Gaelic (ga-IE)</li>
                            <li>Italian (it-IT)</li>
                            <li>Japanese (ja-JP)</li>
                            <li>Kazakh (kk-KZ)</li>
                            <li>Korean (ko-KR)</li>
                            <li>Latvian (lv-LV)</li>
                            <li>Lithuanian (lt-LT)</li>
                            <li>Macedonian (mk-MK)</li>
                            <li>Malay (ms-MY)</li>
                            <li>Maltese (mt-MT)</li>
                            <li>Maori (mi-NZ)</li>
                            <li>Mongolian (mn-MN)</li>
                            <li>Nepali (ne-NP)</li>
                            <li>Norwegian Bokmål (nb-NO)</li>
                            <li>Norwegian Nynorsk (nn-NO)</li>
                            <li>Pakistani (ur-PK)</li>
                            <li>Pashto (ps-PK)</li>
                            <li>Persian (fa-IR)</li>
                            <li>Polish (pl-PL)</li>
                            <li>Portuguese (pt-PT)</li>
                            <li>Portuguese Brazil (pt-BR)</li>
                            <li>Quebecois (fr-CA)</li>
                            <li>Quechua (qu-XN)</li>
                            <li>Romanian (ro-RO)</li>
                            <li>Russian (ru-RU)</li>
                            <li>Serbian Latin (sr-Latn-RS)</li>
                            <li>Serbian Cyrillic (sr-Cyrl-RS)</li>
                            <li>Slovak (sk-SK)</li>
                            <li>Slovenian (sl-SI)</li>
                            <li>Spanish (es-ES)</li>
                            <li>Spanish Latin America (es-MX)</li>
                            <li>Swedish (sv-SE)</li>
                            <li>Swiss German (de-CH)</li>
                            <li>Tamil (ta-LK)</li>
                            <li>Telugu (te-IN)</li>
                            <li>Thai (th-TH)</li>
                            <li>Turkish (tr-TR)</li>
                            <li>Ukrainian (uk-UA)</li>
                            <li>Vietnamese (vi-VN)</li>
                            <li>Welsh (cy-GB)</li>
                        </ul>
                    </td>
                </tr>
                </tbody>
            </table>
            <a class=" gototop" href="#top">Go to top</a>
        </div>

        <div class="block">
            <a name="subjects"><h3 class="method-title">Supported subjects</h3></a>


            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <tr>
                    <th>Subject name</th>
                    <th>Code</th>
                </tr>
                </thead>
                <tbody>
                <tr><td>General </td><td>general</td></tr>
                <tr><td>Accounting &amp; Finance </td><td>accounting_finance</td></tr>
                <tr><td>Aerospace / Defence </td><td>aerospace_defence</td></tr>
                <tr><td>Architecture </td><td>architecture</td></tr>
                <tr><td>Art </td><td>art</td></tr>
                <tr><td>Automotive </td><td>automotive</td></tr>
                <tr><td>Certificates, diplomas, licences, cv's, etc </td><td>certificates_diplomas_licences_cv_etc</td></tr>
                <tr><td>Chemical </td><td>chemical</td></tr>
                <tr><td>Civil Engineering / Construction </td><td>civil_engineering_construction</td></tr>
                <tr><td>Corporate Social Responsibility </td><td>corporate_social_responsibility</td></tr>
                <tr><td>Cosmetics </td><td>cosmetics</td></tr>
                <tr><td>Culinary </td><td>culinary</td></tr>
                <tr><td>Electronics /  Electrical Engineering </td><td>electronics_electrical_engineering</td></tr>
                <tr><td>Energy / Power generation / Oil &amp; Gas </td><td>energy_power_generation_oil_gas</td></tr>
                <tr><td>Environment </td><td>environment</td></tr>
                <tr><td>Fashion </td><td>fashion</td></tr>
                <tr><td>Games / Video Games / Casino </td><td>games_viseogames_casino</td></tr>
                <tr><td>General Business / Commerce </td><td>general_business_commerce</td></tr>
                <tr><td>History / Archaeology </td><td>history_archaeology</td></tr>
                <tr><td>Information Technology </td><td>information_technology</td></tr>
                <tr><td>Insurance </td><td>insurance</td></tr>
                <tr><td>Internet, e-commerce </td><td>internet_e-commerce</td></tr>
                <tr><td>Legal documents / Contracts </td><td>legal_documents_contracts</td></tr>
                <tr><td>Literary Translations </td><td>literary_translations</td></tr>
                <tr><td>Marketing &amp; Advertising material / Public Relations </td><td>marketing_advertising_material_public_relations</td></tr>
                <tr><td>Mathematics and Physics </td><td>matematics_and_physics</td></tr>
                <tr><td>Mechanical / Manufacturing </td><td>mechanical_manufacturing</td></tr>
                <tr><td>Media / Journalism / Publishing </td><td>media_journalism_publishing</td></tr>
                <tr><td>Medical / Pharmaceutical </td><td>medical_pharmaceutical</td></tr>
                <tr><td>Music </td><td>music</td></tr>
                <tr><td>Private Correspondence, Letters </td><td>private_correspondence_letters</td></tr>
                <tr><td>Religion </td><td>religion</td></tr>
                <tr><td>Science </td><td>science</td></tr>
                <tr><td>Shipping / Sailing / Maritime </td><td>shipping_sailing_maritime</td></tr>
                <tr><td>Social Science </td><td>social_science</td></tr>
                <tr><td>Telecommunications </td><td>telecommunications</td></tr>
                <tr><td>Travel &amp; Tourism  </td><td>travel_tourism</td></tr>
                </tbody>
            </table>
            <a class="gototop" href="#top">Go to top</a>
        </div>


        <div class="block">
            <a name="seg-rules"><h3 class="method-title">Supported segmentation rules</h3></a>


            <table class="tablestats" width="100%" border="0" cellspacing="0" cellpadding="0">
                <thead>
                <tr>
                    <th>Segmentation rule name</th>
                    <th>Code</th>
                </tr>
                </thead>
                <tbody>
                <tr><td>General </td><td><code>empty</code></td></tr>
                <tr><td>Patent</td><td>patent</td></tr>

                </tbody>
            </table>
            <a class="last gototop" href="#top">Go to top</a>
        </div>

        <div class="block">
        </div>
    </div>
</div>
<script type="text/javascript">
    jQuery(window).scroll(function() {
        var scroll = jQuery(window).scrollTop();

        if (scroll >= 30) {
            jQuery(".colsx").addClass("menuscroll");
        }
        else {
            jQuery(".colsx").removeClass("menuscroll");
        }

    });

    var position = [];

    jQuery('.block').each(function(){
        position.push(Math.abs(jQuery(this).position().top))
    })

    console.log(position)

    jQuery(window).scroll( function() {

        var value = jQuery(this).scrollTop() + jQuery('.menu').height();

        jQuery.each(position, function(i){
            if(this > value){
                jQuery('.selected').removeClass('selected');
                jQuery(".menu li").eq(i-1).addClass('selected');
                return false;
            }
        })
    });
    jQuery( ".menu a " ).click(function() {
        jQuery(this).addClass('selected');
    });
    jQuery(function() {
        jQuery('a[href*=#]:not([href=#])').click(function() {
            if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname) {
                var target = jQuery(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) +']');
                if (target.length) {
                    jQuery('html,body').animate({
                        scrollTop: target.offset().top
                    }, 1000);
                    return false;
                }
            }
        });
    });
</script>
</body>
</html>
