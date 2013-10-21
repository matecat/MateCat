<?php

set_time_limit(0);
include_once 'main.php';
include_once INIT::$UTILS_ROOT . "/MyMemoryAnalyzer.copyrighted.php";

$ws = new MyMemoryAnalyzer();

while (1) {

	$pid_list = getProjectForVolumeAnalysis('fast', 5);
	if (empty($pid_list)) {
		echo __FILE__ . ":" . __FUNCTION__ . " no projects ready for fast volume analisys: wait 3 seconds\n";
		sleep(3);
		continue;
	}

	echo __FILE__ . ":" . __FUNCTION__ . "  projects found\n";
	print_r($pid_list);

	foreach ($pid_list as $pid_res) {
		$pid = $pid_res['id'];

		$segments=getSegmentsForFastVolumeAnalysys($pid);

		$fastReport = $ws->fastAnalysys($segments);

		$data=$fastReport['data'];
		foreach ($data as $k=>$v){
			if (in_array($v['type'], array("75%-84%","85%-94%","95%-99%"))){
				$data[$k]['type']="INTERNAL";
			}

			if (in_array($v['type'], array("50%-74%"))){
				$data[$k]['type']="NO_MATCH";
			}
		}

        $perform_Tms_Analysis = true;
        $status = "FAST_OK";
        if( $pid_res['id_tms'] == 0 && $pid_res['id_mt_engine'] == 0 ){

            /**
             * MyMemory disabled and MT Disabled Too
             * So don't perform TMS Analysis
             */

            $perform_Tms_Analysis = false;
            $status = "DONE";
            Log::doLog( 'Perform Analysis ' . var_export( $perform_Tms_Analysis, true ) );
        }

		$insertReportRes = insertFastAnalysis($pid,$data, $equivalentWordMapping, $perform_Tms_Analysis);
		if ($insertReportRes < 0) {
			continue;
		}
		$change_res = changeProjectStatus($pid, $status);
		if ($change_res < 0) {
		}
	}
}
?>
