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


		$insertReportRes = insertFastAnalysis($pid,$data, $equivalentWordMapping);
		if ($insertReportRes < 0) {
			continue;
		}
		$change_res = changeProjectStatus($pid, "FAST_OK");
		if ($change_res < 0) {
		}
	}
}
?>
