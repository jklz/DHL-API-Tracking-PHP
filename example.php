<?php
/**********************
Example File

**********************/

require("dhl_tracking.class.php");



################
#
# Start Class
#
################
$track = new dhl_tracking('test');
$track->setAuth("********", "********");

################
#
# Single Request
#
################

$track_one = 1111111111;
$req_one = $track->single($airbill);

print "\n<p>Single Result</p>\n";
print "\n<pre>\n";
print_r($req_one);
print "\n</pre>";

################
#
# Single Request
#
################

$track_multi = array();
$track_multi[] = 1111111111;
$track_multi[] = 1111111112;
$track_multi[] = 1111111113;
$track_multi[] = 1111111114;
$track_multi[] = 1111111115;
$track_multi[] = 1111111116;
$track_multi[] = 1111111117;
$track_multi[] = 1111111118;
$track_multi[] = 1111111119;

$req_multi = $track->multipul($track_multi);

print "\n<p>Multi Result</p>\n";
print "\n<pre>\n";
print_r($req_multi);
print "\n</pre>";

?>