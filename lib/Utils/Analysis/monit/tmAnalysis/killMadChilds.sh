#!/usr/bin/env bash

childs=`ps ax | grep tmAnalysisThreadChild.php | grep -v grep | awk '{print $1}'`
kill childs